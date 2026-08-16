import { spawn, spawnSync } from "node:child_process";
import { config } from "dotenv";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import * as net from "node:net";
import { existsSync, writeFileSync, mkdirSync, unlinkSync } from "node:fs";

const currentFilePath = fileURLToPath(import.meta.url);
const currentDir = dirname(currentFilePath);
const projectRoot = resolve(currentDir, "..");

config({ path: resolve(projectRoot, ".env") });
config();

const preferredPort = Number.parseInt(process.env.PORT || "5000", 10);
const basePort = Number.isFinite(preferredPort) && preferredPort > 0 ? preferredPort : 5000;

const pgDataDir = resolve(projectRoot, ".postgres_data");
const pgLogFile = resolve(pgDataDir, "server.log");
const pgBinDir = process.env.PG_BIN_DIR || (process.platform === "win32"
  ? "C:\\Program Files\\PostgreSQL\\17\\bin"
  : "");

if (process.platform === "win32" && pgBinDir) {
  process.env.PATH = `${pgBinDir};${process.env.PATH || ""}`;
}

function pgCommand(command: string): string {
  if (process.platform === "win32" && pgBinDir) {
    return resolve(pgBinDir, `${command}.exe`);
  }
  return command;
}

function localPgUser(): string {
  // Windows USER/USERNAME is the OS account, not necessarily a PostgreSQL role.
  return process.env.PGUSER || (process.platform === "win32" ? "postgres" : process.env.USER || process.env.USERNAME || "postgres");
}

function hasCommand(command: string): boolean {
  if (process.platform === "win32" && pgBinDir) {
    return existsSync(pgCommand(command));
  }
  const check = spawnSync("sh", ["-lc", `command -v ${command}`], {
    stdio: "ignore",
    shell: false,
  });

  return check.status === 0;
}

function canBootstrapLocalPostgres(): boolean {
  const required = ["pg_isready", "pg_ctl", "psql"];
  const missing = required.filter((cmd) => !hasCommand(cmd));
  if (missing.length > 0) {
    console.warn(
      `⚠️  Skipping local PostgreSQL bootstrap. Missing tools: ${missing.join(", ")}.` +
        " Start Postgres manually or set DATABASE_URL.",
    );
    return false;
  }
  return true;
}

function isPortFree(port: number): Promise<boolean> {
  return new Promise((resolvePort) => {
    const server = net.createServer();
    server.once("error", () => resolvePort(false));
    server.once("listening", () => {
      server.close(() => resolvePort(true));
    });
    server.listen(port, "0.0.0.0");
  });
}

async function isPgReady(port: number): Promise<boolean> {
  return new Promise((resolve) => {
    const check = spawn(pgCommand("pg_isready"), ["-h", "localhost", "-p", String(port)], { stdio: "ignore" });
    check.on("error", () => resolve(false));
    check.on("exit", (code) => resolve(code === 0));
  });
}

function ensurePgLockDirectory(): void {
  if (process.platform === "win32") return;
  // PostgreSQL needs /run/postgresql for its lock file
  const lockDir = "/run/postgresql";
  try {
    if (!existsSync(lockDir)) {
      console.log(`🔧 Creating PostgreSQL lock directory: ${lockDir}`);
      mkdirSync(lockDir, { recursive: true, mode: 0o777 });
    }
    // Clean up stale lock files that may prevent startup
    const stalePid = resolve(pgDataDir, "postmaster.pid");
    const staleSocket = resolve(lockDir, ".s.PGSQL.15432.lock");
    for (const f of [stalePid, staleSocket]) {
      if (existsSync(f)) {
        try {
          unlinkSync(f);
        } catch (_) {
          /* ignore */ }
      }
    }
  } catch (e) {
    console.warn(`⚠️  Could not prepare lock directory: ${e}`);
  }
}

async function startLocalPostgres(): Promise<void> {
  const pgReady = await isPgReady(15432);
  if (pgReady) {
    console.log("✅ Local PostgreSQL already running on port 15432");
    return;
  }

  ensurePgLockDirectory();

  if (!existsSync(pgDataDir)) {
    console.log("🔧 Initializing PostgreSQL data directory...");
    const init = spawn(pgCommand("initdb"), ["--no-locale", "--encoding=UTF8", "-U", localPgUser(), "-D", pgDataDir], { stdio: "inherit" });
    await new Promise((resolve, reject) => {
      init.on("exit", (code) => (code === 0 ? resolve(undefined) : reject(new Error(`initdb failed: ${code}`))));
    });

    const pgConf = resolve(pgDataDir, "postgresql.conf");
    const pgHba = resolve(pgDataDir, "pg_hba.conf");
    writeFileSync(pgConf, "listen_addresses = 'localhost'\nport = 15432\nmax_connections = 20\nshared_buffers = 64MB\n");
    writeFileSync(pgHba, "local   all             all                                     trust\nhost    all             all             127.0.0.1/32            trust\nhost    all             all             ::1/128                 trust\n");
  }

  console.log("🚀 Starting local PostgreSQL on port 15432...");
  const pgStart = spawn(pgCommand("pg_ctl"), ["start", "-D", pgDataDir, "-l", pgLogFile], {
    stdio: "inherit",
    detached: true,
  });

  await new Promise((resolve, reject) => {
    pgStart.on("exit", (code) => {
      if (code === 0) {
        console.log("✅ PostgreSQL started successfully");
        resolve(undefined);
      } else {
        reject(new Error(`pg_ctl start failed: ${code}`));
      }
    });
  });

  // Wait for PostgreSQL to be ready
  for (let i = 0; i < 15; i++) {
    const ready = await isPgReady(15432);
    if (ready) {
      console.log("✅ PostgreSQL is ready");
      return;
    }
    await new Promise((r) => setTimeout(r, 500));
  }

  throw new Error("PostgreSQL did not become ready in time");
}

async function createDatabaseIfNeeded(port = 15432, user = localPgUser(), password?: string): Promise<void> {
  const commandEnv = password ? { ...process.env, PGPASSWORD: password } : process.env;
  return new Promise((resolve, reject) => {
    const check = spawn(pgCommand("psql"), ["-h", "localhost", "-p", String(port), "-U", user, "-d", "postgres", "-c", "SELECT 1 FROM pg_database WHERE datname = 'stellar_dominion';"], {
      stdio: ["ignore", "pipe", "pipe"],
      shell: false,
      env: commandEnv,
    });
    let output = "";
    check.stdout?.on("data", (d) => (output += d));
    check.stderr?.on("data", (d) => (output += d));
    check.on("exit", () => {
      if (output.includes("1 row") || output.includes("(1 row)")) {
        console.log("✅ Database stellar_dominion exists");
        resolve();
        return;
      }
      console.log("🔧 Creating database stellar_dominion...");
      const create = spawn(pgCommand("psql"), ["-h", "localhost", "-p", String(port), "-U", user, "-d", "postgres", "-c", "CREATE DATABASE stellar_dominion;"], {
        stdio: "inherit",
        env: commandEnv,
      });
      create.on("exit", (code) => {
        if (code === 0) {
          console.log("✅ Database stellar_dominion created");
          resolve();
        } else {
          reject(new Error("Failed to create database"));
        }
      });
    });
  });
}

async function findAvailablePort(startPort: number, maxChecks = 25): Promise<number> {
  for (let offset = 0; offset < maxChecks; offset += 1) {
    const candidate = startPort + offset;
    const free = await isPortFree(candidate);
    if (free) return candidate;
  }
  return startPort;
}

async function applyDatabaseSchema(): Promise<void> {
  console.log("🔧 Synchronizing database schema...");
  const command = process.platform === "win32" ? "npx.cmd" : "npx";
  const push = spawn(command, ["drizzle-kit", "push", "--force"], {
    stdio: "inherit",
    shell: process.platform === "win32",
    env: process.env,
  });

  await new Promise<void>((resolve, reject) => {
    push.on("error", reject);
    push.on("exit", (code) => {
      if (code === 0) {
        console.log("✅ Database schema synchronized");
        resolve();
      } else {
        reject(new Error(`drizzle-kit push failed with code ${code}`));
      }
    });
  });
}

async function startDev() {
  // Start local PostgreSQL if DATABASE_URL points to Neon or not set
  const configuredDbUrl = process.env.DATABASE_URL || "";
  const configuredLocalPort = configuredDbUrl.includes("localhost:5432") ? 5432 : null;
  const configuredDbReady = configuredLocalPort ? await isPgReady(configuredLocalPort) : false;
  const dbUrl = configuredDbReady ? configuredDbUrl : configuredDbUrl.replace(/localhost:5432/, "localhost:15432");
  if (configuredDbReady && configuredDbUrl.includes("localhost:5432")) {
    const parsed = new URL(configuredDbUrl);
    await createDatabaseIfNeeded(5432, decodeURIComponent(parsed.username), decodeURIComponent(parsed.password));
  }
  if (!dbUrl || dbUrl.includes("neon.tech") || (configuredLocalPort !== null && !configuredDbReady)) {
    if (canBootstrapLocalPostgres()) {
      await startLocalPostgres();
      await createDatabaseIfNeeded();
      process.env.DATABASE_URL = `postgresql://${localPgUser()}@localhost:15432/stellar_dominion`;
    } else if (!dbUrl) {
      const dockerFallbackUrl =
        process.env.LOCAL_DATABASE_URL || "postgresql://postgres:postgres@localhost:5432/stellar_dominion";
      process.env.DATABASE_URL = dockerFallbackUrl;
      console.log(`ℹ️  Using fallback DATABASE_URL: ${dockerFallbackUrl}`);
    }
  }

  await applyDatabaseSchema();

  const selectedPort = await findAvailablePort(basePort);
  if (selectedPort !== basePort) {
    console.warn(`Port ${basePort} is already in use. Falling back to port ${selectedPort}.`);
  }

  const command = process.execPath;
  const args = [resolve(projectRoot, "node_modules/tsx/dist/cli.mjs"), "server/index.ts"];

  console.log("Starting full-stack development server...");

  const child = spawn(command, args, {
    stdio: "inherit",
    shell: false,
    env: {
      ...process.env,
      NODE_ENV: process.env.NODE_ENV ?? "development",
      PORT: String(selectedPort),
    },
  });

  child.on("exit", (code: number | null) => {
    process.exit(code ?? 0);
  });

  child.on("error", (error: Error) => {
    console.error("Failed to start development process:", error);
    process.exit(1);
  });
}

startDev().catch((error) => {
  console.error("Failed to initialize development server:", error);
  process.exit(1);
});
