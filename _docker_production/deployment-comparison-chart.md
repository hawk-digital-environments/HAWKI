## 📊 Vergleichstabelle: Dev vs. Staging vs. Production

### 🏗️ Build & Deployment

| Feature | **Dev** | **Staging** | **Production** |
|---------|---------|-------------|----------------|
| **Docker Target** | `app_dev` | `app_staging` | `app_prod` |
| **Base Image** | `app_root` + dev tools | `app_root` | `app_root` |
| **Xdebug** | ✅ Installiert | ❌ Nicht installiert | ❌ Nicht installiert |
| **Composer** | Alle Dependencies | `--no-dev` | `--no-dev` |
| **Code Mount** | ✅ Live (RW) | ❌ Im Image | ❌ Im Image |
| **Hot Reload** | ✅ Ja | ❌ Nein | ❌ Nein |
| **Build Cache** | ✅ Verwendet | ✅ Verwendet | ❌ `--no-cache` |
| **Image Pull** | Nein | Bei `--build` | ✅ Immer |
| **Deploy Command** | `./deploy-dev.sh` | deploy-staging.sh | deploy-prod.sh |

### 🔧 Laravel Configuration

| Feature | **Dev** | **Staging** | **Production** |
|---------|---------|-------------|----------------|
| **APP_ENV** | `local` | `staging` | `production` |
| **APP_DEBUG** | `true` | `true` | `false` |
| **LOG_LEVEL** | `debug` | `debug` | `warning` |
| **Cache Routes** | ❌ Nein | ✅ Ja (optional) | ✅ Ja |
| **Cache Config** | ❌ Nein | ✅ Ja | ✅ Ja |
| **Cache Views** | ❌ Nein | ✅ Ja | ✅ Ja |
| **Autoloader** | Normal | Optimized | Classmap Authoritative |

### 📦 Container Configuration

| Feature | **Dev** | **Staging** | **Production** |
|---------|---------|-------------|----------------|
| **Project Name** | `hawki-dev` | `hawki-staging` | `hawki-prod` |
| **Image Tag** | `hawki:dev-local` | `hawki:staging` | `hawki:prod` |
| **Restart Policy** | `no` | `unless-stopped` | `unless-stopped` |
| **UID/GID** | Current user (501/1000) | `www-data` (33/33) | `www-data` (33/33) |
| **Hostname** | `app.hawki.dev` | Custom | Custom |
| **SSL Certs** | Self-signed (local) | Let's Encrypt/Custom | Let's Encrypt/Custom |

### 💾 Volumes & Storage

| Feature | **Dev** | **Staging** | **Production** |
|---------|---------|-------------|----------------|
| **Code Mount** | `..:/var/www/html:rw` | ❌ Nicht gemountet | ❌ Nicht gemountet |
| **Storage Mount** | `./storage:/var/www/html/storage` | `./storage:/var/www/html/storage` | `./storage:/var/www/html/storage` |
| **ENV File** | `./env/.env` (RW) | `./env/.env:ro` | `./env/.env:ro` |
| **MySQL Data** | `mysql_data` (Named) | `mysql_data` (Named) | `mysql_data` (Named) |
| **Redis Data** | `redis_data` (Named) | `redis_data` (Named) | `redis_data` (Named) |
| **Public Dir** | Live Mount | Im Image | Im Image |

### 🗄️ Database Configuration

| Feature | **Dev** | **Staging** | **Production** |
|---------|---------|-------------|----------------|
| **MySQL Port (External)** | `3306` | `3307` | `3306` |
| **MySQL Bind IP** | `127.0.0.1` | `127.0.0.1` | `127.0.0.1` |
| **Default Password** | `password` | `password` | `password` |
| **Auto-Migrate** | ❌ Manual | ✅ Bei Deploy | ✅ Bei Deploy |
| **Auto-Seed** | ❌ Manual | ✅ Bei Deploy | ✅ Bei Deploy |

### 🌐 Network & Ports

| Feature | **Dev** | **Staging** | **Production** |
|---------|---------|-------------|----------------|
| **HTTP Port** | `80` | `80` | `80` |
| **HTTPS Port** | `443` | `443` | `443` |
| **Bind IP** | `0.0.0.0` | `0.0.0.0` | `0.0.0.0` |
| **Adminer** | ✅ Port auto | ❌ Nicht verfügbar | ❌ Nicht verfügbar |
| **Reverb Host** | `reverb` | `reverb` | `reverb` |

### 🔐 Security & Proxy

| Feature | **Dev** | **Staging** | **Production** |
|---------|---------|-------------|----------------|
| **Proxy Support** | ❌ Nein | ✅ Ja | ✅ Ja |
| **HTTP_PROXY** | - | `${DOCKER_HTTP_PROXY}` | `${DOCKER_HTTP_PROXY}` |
| **HTTPS_PROXY** | - | `${DOCKER_HTTPS_PROXY}` | `${DOCKER_HTTPS_PROXY}` |
| **SSL Verification** | Disabled (self-signed) | Enabled | Enabled |
| **Read-Only FS** | ❌ Nein | ❌ Nein | ⚠️ Sollte aktiviert sein |

### 🛠️ Development Tools

| Feature | **Dev** | **Staging** | **Production** |
|---------|---------|-------------|----------------|
| **Adminer** | ✅ Ja | ❌ Nein | ❌ Nein |
| **Composer Binary** | ✅ In Container | ❌ Nur bei Build | ❌ Nur bei Build |
| **Xdebug** | ✅ Ja | ❌ Nein | ❌ Nein |
| **Git** | ✅ Ja | ✅ Ja (für git_info) | ✅ Ja (für git_info) |
| **Node/NPM** | ✅ Ja | ❌ Nur bei Build | ❌ Nur bei Build |
| **Shell Access** | ✅ tmux, bash | ✅ bash | ✅ bash |

### 📋 Services Running

| Service | **Dev** | **Staging** | **Production** |
|---------|---------|-------------|----------------|
| **app** (PHP-FPM) | ✅ | ✅ | ✅ |
| **queue** | ✅ | ✅ | ✅ |
| **reverb** (WebSocket) | ✅ | ✅ | ✅ |
| **scheduler** | ✅ | ✅ | ✅ |
| **nginx** | ✅ | ✅ | ✅ |
| **mysql** | ✅ | ✅ | ✅ |
| **redis** | ✅ | ✅ | ✅ |
| **file-converter** | ✅ | ✅ | ✅ |
| **adminer** | ✅ | ❌ | ❌ |

### 🚀 Deployment Workflow

| Step | **Dev** | **Staging** | **Production** |
|------|---------|-------------|----------------|
| **1. Init ENV** | Optional (`--init`) | Optional (`--init`) | Required (manual) |
| **2. Build Image** | Bei Bedarf | Auto (wenn fehlt) | ✅ Immer |
| **3. Start Containers** | `up -d` | `up -d --build` | `up -d --force-recreate` |
| **4. Migrate DB** | Manual | ✅ Automatisch | ✅ Automatisch |
| **5. Seed DB** | Manual | ✅ Automatisch | ✅ Automatisch |
| **6. Cache Clear** | Manual | ✅ Automatisch | ✅ Automatisch |
| **7. Optimize** | ❌ Nein | ✅ Config/View Cache | ✅ Config/Route/View Cache |
| **8. Git Info** | Manual | ✅ Automatisch | ✅ Automatisch |

### 📝 Configuration Files

| File | **Dev** | **Staging** | **Production** |
|------|---------|-------------|----------------|
| **Compose File** | docker-compose.dev.yml | docker-compose.staging.yml | docker-compose.prod.yml |
| **ENV Template** | .env.dev | .env.staging | `.env.prod` |
| **ENV File** | `env/.env` | `env/.env` | `env/.env` |
| **Nginx Template** | `nginx.template.dev` | `nginx.template.staging` | `nginx.template.prod` |
| **Nginx Config** | `nginx.default.conf` (generated) | `nginx.default.conf` (generated) | `nginx.default.conf` (generated) |

### 🎯 Use Cases

| Use Case | **Dev** | **Staging** | **Production** |
|----------|---------|-------------|----------------|
| **Local Development** | ✅ **Perfekt** | ⚠️ Möglich | ❌ Nicht empfohlen |
| **Testing** | ✅ Unit/Feature Tests | ✅ Integration Tests | ❌ Nicht empfohlen |
| **Demo/Preview** | ⚠️ Nicht stabil | ✅ **Perfekt** | ⚠️ Für wichtige Demos |
| **Production Use** | ❌ Niemals | ❌ Niemals | ✅ **Nur hier** |
| **CI/CD** | ⚠️ Tests | ✅ Auto-Deploy | ✅ Manual Deploy |

### 💡 Empfehlungen

**Dev verwenden für:**
- 🔧 Lokale Entwicklung
- 🐛 Debugging mit Xdebug
- 📝 Code-Änderungen in Echtzeit
- 🗄️ Datenbank-Verwaltung mit Adminer

**Staging verwenden für:**
- 🧪 Integration Testing
- 👥 Team-Demos
- 🔍 Pre-Production Validation
- 🚀 Auto-Deployments von `develop` Branch

**Production verwenden für:**
- 🌐 Live-Website
- 👤 Echte Benutzer
- 📊 Production Data
- 🔒 Maximale Sicherheit & Performance