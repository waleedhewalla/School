# Run Nbras on your computer

Two ways. **A (Docker)** is the easiest: nothing else to install. **B** runs
the app directly and suits developers.

## Get the code

The code is on GitHub (`waleedhewalla/School`, branch `claude/confident-goodall-3cpg1b`).

- With Git:
  ```bash
  git clone -b claude/confident-goodall-3cpg1b https://github.com/waleedhewalla/School.git nbras
  cd nbras
  ```
- Without Git: on GitHub open the repository, switch to the branch above,
  **Code → Download ZIP**, unzip it, and open a terminal in that folder.

## A. With Docker Desktop (Windows, Mac, Linux)

1. Install **Docker Desktop** from docker.com and start it (on Windows it
   asks to turn on WSL 2: accept and restart).
2. In the project folder run:
   ```bash
   docker compose -f compose.local.yml up --build
   ```
   The first run downloads and builds for 5–15 minutes. It is ready when the
   log shows `nginxd entered RUNNING state`.
3. Open **http://localhost:8080**. Emails the app sends (invitations,
   password resets) appear at **http://localhost:8025**.

Stop with `Ctrl+C`. Start again later with the same command (much faster).
Start from a clean demo: `docker compose -f compose.local.yml down -v`, then `up` again.

## B. Without Docker

Install:
- **PHP 8.3** with the `intl`, `pdo_sqlite`, `mbstring`, `zip` and `gd`
  extensions, **Composer**, and **Node.js 22**.
  Easiest on Windows and Mac: **Laravel Herd** (herd.laravel.com), which
  installs PHP and Composer; add Node.js from nodejs.org if Herd's setup did
  not.
- On Ubuntu: `sudo apt install php8.3-cli php8.3-intl php8.3-sqlite3 php8.3-mbstring php8.3-zip php8.3-gd php8.3-xml php8.3-curl composer nodejs npm`

Then, in the project folder:

```bash
composer install
npm install
npm run build
cp .env.example .env            # Windows (PowerShell): copy .env.example .env
php artisan key:generate
php -r "touch('database/database.sqlite');"
php artisan migrate --seed      # SQLite; creates database/database.sqlite and the demo school
php artisan serve
```

Open **http://localhost:8000**. Background jobs (SMS/email notices) run when
you start a worker in a second terminal: `php artisan queue:work`. Messages
are written to `storage/logs/laravel.log` instead of being sent.

## Demo accounts (password: `password`)

| Email | Sees |
|---|---|
| admin@example.com | The whole school: setup, students, admissions, analytics, payroll |
| teacher@example.com | Grade 1 / أ: attendance, marks, homework, quizzes, behaviour |
| parent@example.com | Two children: attendance, homework, results, bus, health card |
| student@example.com | Own quizzes and homework |
| platform@example.com | The operator console: all schools (two-factor sign-in not required locally) |

The public admission form is at `/apply/demo`.

## Problems

| You see | Do |
|---|---|
| `port is already allocated` / `address already in use` | Something else uses 8080 (or 8000). Close it, or change `8080:80` in `compose.local.yml` to e.g. `8090:80` and open localhost:8090. |
| `Cannot connect to the Docker daemon` | Start Docker Desktop and wait until it says it is running. |
| Page shows `Vite manifest not found` (option B) | Run `npm run build`. |
| `could not find driver` (option B) | The PHP SQLite extension is missing; install it (see above). |
| Blank page or 500 error | Look at the terminal log (A) or `storage/logs/laravel.log` (B). |
