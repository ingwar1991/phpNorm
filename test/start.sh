#!/bin/bash

cd docker || exit

CONTAINER_NAME='norm_test_php'

COLOR_RED='\033[31m'
COLOR_GREEN='\033[32m'
COLOR_YELLOW='\033[33m'
COLOR_RESET='\033[0m'

detach=''
toBash=0
runTests=0
stopContainers=0

if [ -n "$1" ] 
then
    if [ "$1" = "-d" ] 
    then
        detach="-d"
    elif [ "$1" = "-b" ] 
    then
        detach="-d"
        toBash=1
    elif [ "$1" = "-t" ] 
    then
        detach="-d"
        runTests=1
    elif [ "$1" = "-s" ] 
    then
        detach="-d"
        stopContainers=1
    else
        echo -e "Usage: ./start {param} \n \
Parameters: \n \
    -d - run containers & detach \n \
    -b - run containers & go to php container bash \n \
    -t - run containers, then run tests & stop on success, on error go to php container bash \n \
    -s - stop containers"
        exit
    fi
fi

if [ "$stopContainers" -eq 1 ] 
then
    echo -e "${COLOR_YELLOW}***Stopping containers***${COLOR_RESET}"
    docker compose down

    exit 0
fi

echo -e "${COLOR_YELLOW}***Starting containers***${COLOR_RESET}"
docker compose up $detach

if [ $? -ne 0 ]
then
    echo -e "${COLOR_RED}Failed to start containers.${COLOR_RESET}"
    exit 1
fi

if [ "$runTests" -eq 1 ] 
then
    echo -e "${COLOR_YELLOW}***Preparing for tests***${COLOR_RESET}"

    echo "Waiting for container to start..."
    until docker ps --filter "name=$CONTAINER_NAME" --filter "status=running" | grep -q "$CONTAINER_NAME"; do
        echo "Container is not ready yet, waiting..."
        sleep 2
    done

    echo "Running PHPUnit tests..."
    docker compose exec "$CONTAINER_NAME" ./runTests.sh

    if [ $? -eq 0 ]; then
        echo -e "${COLOR_GREEN}Tests passed successfully!${COLOR_RESET}"
        
        # Stop the containers if tests passed
        stopContainers=1
    else
        echo -e "${COLOR_RED}Tests failed. Container will remain running.${COLOR_RESET}"
        # go to php container if tests failed
        toBash=1
    fi
fi

if [ "$toBash" -eq 1 ]
then
    echo -e "${COLOR_YELLOW}***Going to php bash***${COLOR_RESET}"
    docker compose exec "${CONTAINER_NAME}" bash
fi

if [ "$stopContainers" -eq 1 ] 
then
    echo -e "${COLOR_YELLOW}***Stopping containers***${COLOR_RESET}"
    docker compose down
fi
