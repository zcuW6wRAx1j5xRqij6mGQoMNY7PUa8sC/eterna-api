#!/bin/bash

# Exit on any error
set -e

# Check if required commands exist
command -v docker >/dev/null 2>&1 || { echo "Docker is not installed. Aborting." >&2; exit 1; }
command -v git >/dev/null 2>&1 || { echo "Git is not installed. Aborting." >&2; exit 1; }

# --- 1. 权限同步设置 (关键步骤) ---
# 获取宿主机当前用户的 UID 和 GID
# 这些变量将被传递到 docker-compose.yml 并用于 Dockerfile 的 development 阶段
export USER_ID=$(id -u)
export GROUP_ID=$(id -g)

echo "Setting container user ID to match host user (UID: $USER_ID, GID: $GROUP_ID)"
# -----------------------------------------------------------------------

# 确保旧的 compose 容器被停止和移除
# 使用 --remove-orphans 确保清理了所有旧的服务
docker compose down --remove-orphans

# --- 2. 镜像版本和构建 ---
# 使用 Git Commit ID 或其他方式作为标签是很好的习惯，即使在开发中也保持清晰的版本
GIT_COMMIT=$(git rev-parse --short HEAD)
echo "Using Git Commit ID: $GIT_COMMIT"
export GIT_COMMIT=$GIT_COMMIT

# 注意：我们假设 Dockerfile-base 的内容已经合并到了主 Dockerfile 中，
#      或者 BASE_IMAGE 在构建主服务时会自动拉取。
#      在开发环境中，我们不再需要手动管理 BASE_IMAGE 的存在，
#      因为 docker compose build 会处理整个依赖链和 multi-stage build。
# 如果您的主服务（php）配置了 build，且 image 标签为 netblaze/eterna-api:dev-local

# 构建所有服务。Docker Compose 会将 $UID 和 $GID 自动传递给 Dockerfile (通过 docker-compose.yml 中的 args)。
# 注意：这里我们同时传递 GIT_COMMIT 作为 build-arg (尽管权限同步不需要它，但可能用于镜像元数据)
echo "Building Docker images..."
docker compose build --build-arg GIT_COMMIT=$GIT_COMMIT

# --- 3. 启动服务 ---
echo "Starting services..."
docker compose up -d

# Wait for services to start
sleep 5

# Check if services are running
echo "Checking service status..."
docker compose ps

echo "Containers started with image tag: $GIT_COMMIT"
echo "Development environment successfully initialized with host user permissions."



# 单独处理composer 
# docker run --rm -it \
#  -v ./:/var/www/html \
#  -w /var/www/html \
#  --user "$(id -u):$(id -g)" \
#  php-cli:latest \
#  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader