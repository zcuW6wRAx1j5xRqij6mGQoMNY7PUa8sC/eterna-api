#!/bin/bash

# 获取 git commit ID
GIT_COMMIT=$(git rev-parse --short HEAD)
echo "Using Git Commit ID: $GIT_COMMIT"

# 设置环境变量并构建
export GIT_COMMIT=$GIT_COMMIT

# 构建并启动容器
docker compose build --build-arg GIT_COMMIT=$GIT_COMMIT
docker compose up -d

echo "Containers started with image tag: $GIT_COMMIT"