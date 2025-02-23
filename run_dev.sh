#!/bin/bash

# Function to start workers
start_workers() {
    echo "Starting queue workers..."
    php artisan queue:work

    echo "Starting mail queue..."
    php artisan queue:work --queue=mails

    echo "Starting message broadcast queue..."
    php artisan queue:work --queue=message_broadcast
}

# Function to stop workers
stop_workers() {
    echo "Stopping workers..."
    if [[ -f worker1.pid ]]; then
        kill $(cat worker1.pid)
    fi

    if [[ -f worker2.pid ]]; then
        kill $(cat worker2.pid)
    fi

    if [[ -f worker3.pid ]]; then
        kill $(cat worker3.pid)
    fi
}

# Check command line argument to either start or stop
case $1 in
    start)
        start_workers
        ;;
    stop)
        stop_workers
        ;;
    *)
        echo "Usage: $0 {start|stop}"
        ;;
esac