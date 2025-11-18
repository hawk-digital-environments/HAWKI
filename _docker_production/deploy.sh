#!/bin/bash

docker compose up --pull always -d

# Stop if the previous command failed
if [ $? -ne 0 ]; then
    echo "Docker compose up failed"
    exit 1
fi
