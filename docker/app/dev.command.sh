#!/bin/bash

SESSION_NAME="laravel-dev"

# Check if the session already exists
if tmux has-session -t "$SESSION_NAME" 2>/dev/null; then
    echo "Session '$SESSION_NAME' already exists. Attaching."
    tmux attach-session -t "$SESSION_NAME"
    exit 0
fi

trap 'tmux kill-session -t "$SESSION_NAME" 2>/dev/null' EXIT

tmux new-session -d -s "$SESSION_NAME" -n "Services"

# Give the session a moment to initialize
sleep 0.1

tmux split-window -h -t "$SESSION_NAME:0.0"   # Pane 0 -> 1 (horizontal)
tmux send-keys -t "$SESSION_NAME:0.0" 'composer run queue' Enter
tmux send-keys -t "$SESSION_NAME:0.1" 'composer run websocket' Enter

tmux set-option -t "$SESSION_NAME" remain-on-exit on

# Bind Ctrl+Q to kill session and exit (much easier than Ctrl+B D)
tmux bind-key -n C-q kill-session

echo "Attaching to tmux session '$SESSION_NAME'."
echo "Press Ctrl+Q to stop all services and exit."
read -n 1 -s -r -p "Press any key to continue"
echo ""

tmux attach-session -t "$SESSION_NAME"
