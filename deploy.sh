#!/bin/bash

# 确保旧的compose容器被停止和移除
docker compose down

GIT_COMMIT=$(git rev-parse --short HEAD)
echo "Using Git Commit ID: $GIT_COMMIT"

# 检查基础镜像是否存在
BASE_IMAGE="netblaze/php-fpm-apache:latest"
if [[ "$(docker images -q $BASE_IMAGE 2> /dev/null)" == "" ]]; then
    echo "Base image $BASE_IMAGE not found. Building from Dockerfile-base..."
    docker build -f Dockerfile-base -t $BASE_IMAGE .
    if [ $? -eq 0 ]; then
        echo "Base image built successfully: $BASE_IMAGE"
    else
        echo "Failed to build base image. Exiting..."
        exit 1
    fi
else
    echo "Base image $BASE_IMAGE already exists. Skipping build."
fi

export GIT_COMMIT=$GIT_COMMIT

docker compose build --build-arg GIT_COMMIT=$GIT_COMMIT
docker compose up -d

echo "Containers started with image tag: $GIT_COMMIT"