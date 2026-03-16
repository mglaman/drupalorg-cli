#!/bin/zsh

set -euo pipefail

REPO_ROOT=${0:A:h:h}
function compdef() {}
source "$REPO_ROOT/drupalorg-cli-completion.zsh"

function assert_eq() {
  local expected=$1
  local actual=$2
  local message=$3

  if [[ "$expected" != "$actual" ]]; then
    print -u2 -- "ASSERTION FAILED: $message"
    print -u2 -- "Expected: $expected"
    print -u2 -- "Actual:   $actual"
    exit 1
  fi
}

function assert_contains() {
  local haystack=$1
  local needle=$2
  local message=$3

  if [[ " $haystack " != *" $needle "* ]]; then
    print -u2 -- "ASSERTION FAILED: $message"
    print -u2 -- "Missing: $needle"
    print -u2 -- "In: $haystack"
    exit 1
  fi
}

function assert_not_contains() {
  local haystack=$1
  local needle=$2
  local message=$3

  if [[ " $haystack " == *" $needle "* ]]; then
    print -u2 -- "ASSERTION FAILED: $message"
    print -u2 -- "Unexpected: $needle"
    print -u2 -- "In: $haystack"
    exit 1
  fi
}

typeset json_fixture
json_fixture=$(cat <<'EOF'
{
  "application": {
    "name": "Drupal.org CLI",
    "version": "0.8.3"
  },
  "commands": [
    {
      "name": "list",
      "hidden": false,
      "usage": ["list [--format FORMAT] [--] [<namespace>]"],
      "definition": {
        "arguments": {
          "namespace": {
            "name": "namespace",
            "is_required": false,
            "is_array": false
          }
        },
        "options": {
          "format": {
            "name": "--format",
            "shortcut": "",
            "accept_value": true,
            "is_multiple": false
          },
          "help": {
            "name": "--help",
            "shortcut": "-h",
            "accept_value": false,
            "is_multiple": false
          }
        }
      }
    },
    {
      "name": "issue:checkout",
      "hidden": false,
      "usage": ["issue:checkout [<nid> [<branch>]]"],
      "definition": {
        "arguments": {
          "nid": {
            "name": "nid",
            "is_required": false,
            "is_array": false
          },
          "branch": {
            "name": "branch",
            "is_required": false,
            "is_array": false
          }
        },
        "options": {
          "help": {
            "name": "--help",
            "shortcut": "-h",
            "accept_value": false,
            "is_multiple": false
          }
        }
      }
    },
    {
      "name": "issue:search",
      "hidden": false,
      "usage": [
        "issue:search [-s|--status [STATUS]] [--limit [LIMIT]] [-f|--format [FORMAT]] [--] [<project> [<query>]]",
        "is"
      ],
      "definition": {
        "arguments": {
          "project": {
            "name": "project",
            "is_required": false,
            "is_array": false
          },
          "query": {
            "name": "query",
            "is_required": false,
            "is_array": false
          }
        },
        "options": {
          "status": {
            "name": "--status",
            "shortcut": "-s",
            "accept_value": true,
            "is_multiple": false
          },
          "format": {
            "name": "--format",
            "shortcut": "-f",
            "accept_value": true,
            "is_multiple": false
          }
        }
      }
    },
    {
      "name": "issue:show",
      "hidden": false,
      "usage": ["issue:show [-f|--format [FORMAT]] [--with-comments] [--] <nid>"],
      "definition": {
        "arguments": {
          "nid": {
            "name": "nid",
            "is_required": true,
            "is_array": false
          }
        },
        "options": {
          "format": {
            "name": "--format",
            "shortcut": "-f",
            "accept_value": true,
            "is_multiple": false
          },
          "with-comments": {
            "name": "--with-comments",
            "shortcut": "",
            "accept_value": false,
            "is_multiple": false
          },
          "help": {
            "name": "--help",
            "shortcut": "-h",
            "accept_value": false,
            "is_multiple": false
          }
        }
      }
    }
  ],
  "namespaces": [
    {
      "id": "_global",
      "commands": ["is", "list"]
    },
    {
      "id": "issue",
      "commands": ["issue:checkout", "issue:search", "issue:show"]
    }
  ]
}
EOF
)

_drupalorg_reset_completion_cache
_drupalorg_load_json_completion_cache "$json_fixture"

assert_eq "issue:search" "$(_drupalorg_resolve_command_name is)" "Alias should resolve to canonical command"
assert_eq "issue:show" "$(_drupalorg_resolve_command_name issue:show)" "Canonical command should resolve to itself"

typeset issue_show_options
issue_show_options=$(_drupalorg_collect_option_matches issue:show "--" "--format")
assert_contains "$issue_show_options" "--with-comments" "issue:show should expose long options"
assert_not_contains "$issue_show_options" "--format" "Non-repeatable used option should not be re-suggested"

typeset issue_show_short_options
issue_show_short_options=$(_drupalorg_collect_option_matches issue:show "-" "-f")
assert_contains "$issue_show_short_options" "-h" "issue:show should expose short options"
assert_not_contains "$issue_show_short_options" "-f" "Used short option should not be re-suggested"

typeset issue_show_arguments
issue_show_arguments=$(_drupalorg_collect_argument_placeholders issue:show 1)
assert_contains "$issue_show_arguments" "<nid>" "Required positional argument should be exposed as placeholder"

typeset issue_checkout_arguments
issue_checkout_arguments=$(_drupalorg_collect_argument_placeholders issue:checkout 2)
assert_contains "$issue_checkout_arguments" "[branch]" "Optional positional argument should be exposed as placeholder"

typeset top_level_matches
top_level_matches=$(_drupalorg_collect_top_level_matches "")
assert_contains "$top_level_matches" "issue:" "Namespaces should be offered at top level"
assert_contains "$top_level_matches" "is" "Aliases should be offered at top level"
assert_contains "$top_level_matches" "list" "Top-level commands should be offered at top level"

typeset invocation_log
invocation_log=$(mktemp)
function drupalorg() {
  print -- "list --format=json" >> "$invocation_log"
  if [[ "$1" == "list" && "$2" == "--format=json" ]]; then
    print -r -- "$json_fixture"
    return 0
  fi

  print -u2 -- "Unexpected drupalorg invocation: $*"
  return 1
}

_drupalorg_reset_completion_cache
_drupalorg_maybe_load_completion_cache
_drupalorg_maybe_load_completion_cache
assert_eq "1" "$(wc -l < "$invocation_log" | tr -d ' ')" "Session cache should fetch JSON once"
rm -f "$invocation_log"

print -- "zsh completion tests passed"
