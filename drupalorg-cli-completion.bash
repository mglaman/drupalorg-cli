#!/bin/bash

_drupalorgcli_complete() {
    local cur prev words cword command
    _init_completion -n : || return

    # This is a minimal completion - global options and options for each command is not completed.
    # Many edge cases are not covered, for example completion after command option is wrong.

    COMPREPLY=()

    # Stop completing if command is already present, except for "help".
    # (This check fails when options follows command.)
    if [[ -n $prev ]] && [[ $prev != "help" ]]; then
        command=$(compgen -W "$(drupalorg complete)" -- "$prev")
        if [[ $command == $prev ]]; then
            return 0
        fi
    fi

    COMPREPLY=( $(compgen -W "$(drupalorg complete)" -- "$cur") )
    __ltrim_colon_completions "$cur"
} &&
complete -F _drupalorgcli_complete drupalorg
