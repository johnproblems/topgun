#!/bin/bash
set -a
source .env
set +a
npx --yes task-master-ai "$@" 2>&1 | grep -v "MCP\|FastMCP\|jsonrpc\|notifications"