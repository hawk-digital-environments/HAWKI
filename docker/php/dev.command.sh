#!/bin/bash

session_name="bin-env-dev"

# Cleanup on exit
trap 'tmux kill-session -t "$session_name"' EXIT INT

tmux new-session -d -s "$session_name"
tmux set-option -t "$session_name" remain-on-exit off
tmux split-window -h
tmux send-keys -t "$session_name":0.0 'composer run queue' Enter
tmux send-keys -t "$session_name":0.1 'composer run websocket' Enter
tmux attach-session -t "$session_name"
tmux kill-session -t "$session_name"
