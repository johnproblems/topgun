#!/bin/bash
# Helper script to run Docker commands with proper group

# This script runs commands in the docker group context
exec sg docker -c "docker $*"