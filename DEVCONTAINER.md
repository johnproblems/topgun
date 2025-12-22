# 🚀 Dev Container Quick Start

This repository includes a **fully configured development container** that provides a complete development environment with all tools, dependencies, and services pre-installed.

## ✨ What You Get

### 🛠️ Pre-installed Tools
- **PHP 8.4** with all required extensions
- **Node.js 20 LTS** with npm, yarn, and pnpm
- **PostgreSQL 15**, **Redis 7**, **Docker**, **Terraform**
- **Composer**, **Laravel Installer**, and PHP dev tools

### 🤖 AI Assistant CLIs
- **GitHub Copilot CLI** - AI-powered terminal assistant
- **Google Gemini CLI** - Via gcloud CLI
- **Claude API Client** - Anthropic's Claude API
- **GitHub CLI** - Full GitHub operations

### 📦 Development Services
- **Laravel** application server
- **Vite** dev server with hot reload
- **PostgreSQL** database
- **Redis** for caching and queues
- **Mailpit** for email testing
- **MinIO** for S3-compatible storage

### 🔌 VS Code Extensions (40+)
- PHP IntelliSense, Laravel, Blade formatting
- Vue.js 3 (Volar), Tailwind CSS
- Docker, Terraform, PostgreSQL
- GitHub Copilot, GitLens, ESLint, Prettier
- And many more productivity tools!

## 🎯 Quick Start

### Option 1: VS Code (Recommended)
1. Install [VS Code](https://code.visualstudio.com/) and [Remote - Containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) extension
2. Open this folder in VS Code
3. Click "Reopen in Container" when prompted
4. Wait for setup to complete (10-15 minutes first time)
5. Start coding! ✨

### Option 2: GitHub Codespaces
1. Click "Code" → "Codespaces" → "Create codespace"
2. Wait for environment to initialize
3. Start developing in your browser! 🌐

## 📚 Full Documentation

See [.devcontainer/README.md](.devcontainer/README.md) for:
- Detailed setup instructions
- Service configuration
- AI CLI tool usage guides
- Troubleshooting tips
- Customization options

## 🔗 Quick Links

| Service | URL | Credentials |
|---------|-----|-------------|
| Laravel App | http://localhost:8000 | test@example.com / password |
| Vite Dev | http://localhost:5173 | - |
| Mailpit | http://localhost:8025 | - |
| MinIO | http://localhost:9001 | minioadmin / minioadmin |

## 💡 Common Commands

```bash
# Start application
php artisan serve              # Laravel app
npm run dev                    # Vite dev server
php artisan queue:work         # Queue worker
php artisan reverb:start       # WebSocket server

# Or use helper script
./dev.sh start                 # Start all services

# Code quality
./vendor/bin/pint              # Format code
./vendor/bin/phpstan analyse   # Static analysis
./vendor/bin/pest              # Run tests

# AI assistants
gh copilot suggest "..."       # GitHub Copilot
gcloud ai ...                  # Gemini CLI
```

## 🤝 Contributing

This dev container is designed to provide a consistent development environment for all contributors. If you need to add tools or modify configuration, please update the files in `.devcontainer/` and document changes.

---

**Ready to start?** Open this folder in VS Code and click "Reopen in Container"! 🚀
