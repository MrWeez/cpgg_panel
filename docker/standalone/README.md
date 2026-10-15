# 🐳 Standalone Docker

The CtrlPanel standalone Docker enables users to run CtrlPanel easily with just a few clicks.

To run CtrlPanel standalone Docker, you need to have Docker installed on your machine. Some server operating systems like Unraid, TrueNAS, etc.. already have Docker installed, making it even easier to run CtrlPanel.
If you're using a different operating system, you can follow the official Docker installation guide [here](https://docs.docker.com/get-docker/).

Once you have Docker installed, you can run CtrlPanel standalone Docker by executing the following command:

Running as commandline command needs external MariaDB + Redis, compose below is recommended:

```bash
docker run -p 9010:80 ghcr.io/ctrlpanel-gg/panel:latest
```

Plain HTTP only, TLS terminates at your reverse proxy / tunnel. Image defaults (`DB_HOST=mariadb`, `REDIS_HOST=redis`) already work with compose, no vars required.

This command will run the latest CtrlPanel Docker image from GitHub Container Registry and run it.

Recommended way via Docker Compose:

1. Copy compose file and docker env template:
```bash
curl -L -o compose.yaml https://raw.githubusercontent.com/Ctrlpanel-gg/panel/main/docker/standalone/compose.yaml
curl -L -o .env https://raw.githubusercontent.com/Ctrlpanel-gg/panel/main/docker/standalone/.env.example
```
Edit `.env` (`MYSQL_*` for db, `CTRLPANEL_DB_*` mapped from them by default, panel `.env` inside container is written by installer).
2. Create the panel env file `touch panel.env`. Persistent by default: `panel.env` (installer settings), `./storage` (logs, uploads, cache), `mariadb-data` volume (database). DB host `mariadb` and Redis host `redis` are hardcoded from compose network, prefilled in installer with live credentials. Host port is `9010:80` in compose, edit it there directly. Optional full-code / nginx mounts are commented in compose.

The control panel will be available at http://localhost:9010/installer and will be a completely fresh installation.

Note that while the container contains the full CtrlPanel installation, you will still need to perform the basic setup. You can find instructions for this [here](https://ctrlpanel.gg/docs/Installation/getting-started#basic-setup).

## 🏗️ Migrating from a previous bare metal installation

If you are migrating, from a previous bare metal installation, you can follow the instructions [here]() (Soon on documentation).

## 🧰 Creating your own Docker image

If you want to create your own Docker image, you can follow the instructions [here]() (Soon on documentation).
