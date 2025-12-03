# Why Your Environment File Didn't Run On Launch

## The Issue

The `.github/copilot-setup-steps.yml` file is **documentation for GitHub Copilot**, not an executable script. It helps Copilot understand your project structure and setup requirements, but it doesn't run automatically when you open a workspace.

## Understanding GitHub Copilot Setup Files

### What `.github/copilot-setup-steps.yml` Is:
- ✅ **Documentation** for GitHub Copilot to read
- ✅ **Reference material** for developers
- ✅ **Structured information** about your project setup
- ✅ **Used by Copilot** to give better suggestions

### What It Is NOT:
- ❌ **Not an executable script** that runs automatically
- ❌ **Not a GitHub Action** workflow
- ❌ **Not triggered** on repository clone or workspace open
- ❌ **Not a VS Code task** or devcontainer feature

## The Solution

### Option 1: Use the New Setup Script (Recommended)

We've created an executable setup script for you:

```bash
./setup-env.sh
```

This script will:
1. Check prerequisites (PHP, Composer, Node.js, npm)
2. Create `.env` file from `.env.development.example`
3. Generate Laravel application key
4. Install PHP dependencies via Composer
5. Install Node.js dependencies via npm
6. Show you next steps

### Option 2: Manual Setup

Follow these steps manually:

```bash
# 1. Create environment file
cp .env.development.example .env

# 2. Generate application key
php artisan key:generate

# 3. Install PHP dependencies
composer install

# 4. Install Node.js dependencies
npm install

# 5. Configure database in .env
# Edit .env and set:
# - DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 6. Run migrations
php artisan migrate

# 7. (Optional) Seed test data
php artisan db:seed --class=EnterpriseSeeder
```

### Option 3: Use Docker Development Environment

```bash
# Using the dev.sh script
./dev.sh start

# Or if you have Spin installed
spin up
```

## Why This Happens

GitHub Copilot's setup files work differently than other automation systems:

| System | File | Auto-Runs? | Purpose |
|--------|------|-----------|---------|
| **GitHub Actions** | `.github/workflows/*.yml` | ✅ Yes (on push/PR) | CI/CD automation |
| **VS Code Tasks** | `.vscode/tasks.json` | ✅ Can auto-run | Task automation |
| **Devcontainers** | `.devcontainer/devcontainer.json` | ✅ Yes (on container start) | Container setup |
| **GitHub Copilot** | `.github/copilot-setup-steps.yml` | ❌ No | Documentation only |
| **npm** | `package.json` scripts | ⚠️ Manual | `npm run <script>` |
| **Composer** | `composer.json` scripts | ⚠️ Manual | `composer <script>` |

## How to Enable Auto-Setup

If you want automatic setup on workspace launch, you have several options:

### Option A: VS Code Task (Auto-runs on folder open)

Create `.vscode/tasks.json`:

```json
{
  "version": "2.0.0",
  "tasks": [
    {
      "label": "Setup Environment",
      "type": "shell",
      "command": "./setup-env.sh",
      "runOptions": {
        "runOn": "folderOpen"
      },
      "presentation": {
        "reveal": "always",
        "panel": "new"
      },
      "problemMatcher": []
    }
  ]
}
```

### Option B: Devcontainer (Auto-runs on container creation)

Create `.devcontainer/devcontainer.json`:

```json
{
  "name": "Coolify Enterprise Dev",
  "dockerComposeFile": "../docker-compose.dev.yml",
  "service": "coolify",
  "workspaceFolder": "/var/www/html",
  "postCreateCommand": "./setup-env.sh",
  "forwardPorts": [8000, 5173, 6001],
  "customizations": {
    "vscode": {
      "extensions": [
        "bmewburn.vscode-intelephense-client",
        "vue.volar",
        "bradlc.vscode-tailwindcss"
      ]
    }
  }
}
```

### Option C: Git Hooks (Auto-runs after clone)

Create `.git/hooks/post-checkout`:

```bash
#!/bin/bash
if [ -f setup-env.sh ]; then
    echo "Running initial setup..."
    ./setup-env.sh
fi
```

Then make it executable:
```bash
chmod +x .git/hooks/post-checkout
```

## What GitHub Copilot DOES Use the File For

Even though it doesn't run automatically, the `copilot-setup-steps.yml` file is still valuable:

1. **Contextual Suggestions**: Copilot reads it to understand your tech stack
2. **Command Recommendations**: Copilot can suggest the right commands from it
3. **Troubleshooting Help**: Copilot references it when you have setup issues
4. **Documentation**: It serves as structured documentation for the team

## Example: How to Ask Copilot for Help

Instead of expecting auto-setup, you can ask Copilot:

```
@workspace How do I set up this project?
```

Copilot will read `copilot-setup-steps.yml` and give you personalized setup instructions.

## Current State of Your Environment

Based on the runner environment:
- ✅ PHP 8.3.6 installed (8.4+ recommended for this project)
- ✅ Git repository cloned
- ❌ No `.env` file (needs creation)
- ❌ No `vendor/` directory (needs `composer install`)
- ❌ No `node_modules/` directory (needs `npm install`)

## Quick Start

Run the setup script now:

```bash
./setup-env.sh
```

Or if you prefer Docker:

```bash
./dev.sh start
```

## Need Help?

- 📖 See `CLAUDE.md` for comprehensive development guidelines
- 📖 See `.github/copilot-setup-steps.yml` for detailed setup steps
- 📖 See `README.md` for project overview
- 💬 Ask GitHub Copilot: `@workspace how do I...`

## Related Files

- `setup-env.sh` - New executable setup script (✨ just created!)
- `.github/copilot-setup-steps.yml` - Copilot documentation
- `.env.development.example` - Environment template
- `dev.sh` - Docker development script
- `CLAUDE.md` - Development guidelines
- `composer.json` - PHP dependencies
- `package.json` - Node.js dependencies

---

**TL;DR**: The Copilot setup file is documentation, not a script. Run `./setup-env.sh` to actually set up your environment.
