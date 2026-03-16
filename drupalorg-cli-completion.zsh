#compdef drupalorg

# Enhanced completion data is cached in shell memory for the current session.
# After upgrading drupalorg, either start a new shell, call
# `_drupalorg_reset_completion_cache`, or set `DRUPALORG_COMPLETION_RESET=1`
# before the next completion request to force a rebuild.

typeset -g _DRUPALORG_COMPLETION_CACHE_READY=0
typeset -ga _DRUPALORG_TOP_LEVEL_MATCHES
typeset -ga _DRUPALORG_ALL_COMMANDS
typeset -gA _DRUPALORG_ALIAS_TO_COMMAND
typeset -gA _DRUPALORG_COMMAND_ARGUMENTS
typeset -gA _DRUPALORG_COMMAND_LONG_OPTIONS
typeset -gA _DRUPALORG_COMMAND_SHORT_OPTIONS
typeset -gA _DRUPALORG_COMMAND_OPTION_CANONICAL
typeset -gA _DRUPALORG_COMMAND_OPTION_TAKES_VALUE
typeset -gA _DRUPALORG_COMMAND_OPTION_REPEATABLE

_drupalorg_reset_completion_cache() {
  typeset -g _DRUPALORG_COMPLETION_CACHE_READY=0
  typeset -ga _DRUPALORG_TOP_LEVEL_MATCHES=()
  typeset -ga _DRUPALORG_ALL_COMMANDS=()
  typeset -gA _DRUPALORG_ALIAS_TO_COMMAND=()
  typeset -gA _DRUPALORG_COMMAND_ARGUMENTS=()
  typeset -gA _DRUPALORG_COMMAND_LONG_OPTIONS=()
  typeset -gA _DRUPALORG_COMMAND_SHORT_OPTIONS=()
  typeset -gA _DRUPALORG_COMMAND_OPTION_CANONICAL=()
  typeset -gA _DRUPALORG_COMMAND_OPTION_TAKES_VALUE=()
  typeset -gA _DRUPALORG_COMMAND_OPTION_REPEATABLE=()
}

_drupalorg_append_unique_array_entry() {
  emulate -L zsh
  setopt local_options no_sh_word_split typesetsilent

  local array_name=$1
  local value=$2
  local -a target_array

  target_array=("${(@P)array_name}")
  (( ${target_array[(I)$value]} )) && return
  eval "${array_name}+=(\"\$value\")"
}

_drupalorg_append_unique_assoc_list() {
  emulate -L zsh
  setopt local_options no_sh_word_split typesetsilent

  local assoc_name=$1
  local key=$2
  local value=$3
  local existing_values=()
  local current_value=""

  current_value=${${(P)assoc_name}[$key]-}

  if [[ -n $current_value ]]; then
    existing_values=("${(@s: :)current_value}")
    (( ${existing_values[(I)$value]} )) && return
    eval "${assoc_name}+=(\"\$key\" \"\$current_value \$value\")"
    return
  fi

  eval "${assoc_name}+=(\"\$key\" \"\$value\")"
}

_drupalorg_load_json_completion_cache() {
  emulate -L zsh
  setopt local_options no_sh_word_split typesetsilent

  local json_payload=$1
  local jq_output
  local kind command_name field_one field_two field_three field_four field_five
  local record_separator=$'\x1f'

  _drupalorg_reset_completion_cache

  jq_output=$({
    printf '%s' "$json_payload" | jq -r --arg sep "$record_separator" '.namespaces[]? | select(.id != "_global") | ["NAMESPACE", .id] | join($sep)'
    printf '%s' "$json_payload" | jq -r --arg sep "$record_separator" '.commands[]? | select(.hidden | not) | ["COMMAND", .name] | join($sep)'
    printf '%s' "$json_payload" | jq -r '
      .commands[]?
      | select(.hidden | not) as $command
      | ($command.usage[1:][]? | select(type == "string" and test("^[^[:space:]]+$")))
      | ["ALIAS", ., $command.name]
      | join("\u001f")
    '
    printf '%s' "$json_payload" | jq -r '
      .commands[]?
      | select(.hidden | not) as $command
      | ($command.definition.options // {})
      | to_entries[]
      | [
          "OPTION",
          $command.name,
          .key,
          (.value.name // ""),
          (.value.shortcut // ""),
          (if .value.accept_value then "1" else "0" end),
          (if .value.is_multiple then "1" else "0" end)
        ]
      | join("\u001f")
    '
    printf '%s' "$json_payload" | jq -r '
      .commands[]?
      | select(.hidden | not) as $command
      | (
          if (($command.definition.arguments // []) | type) == "array" then
            ($command.definition.arguments // [])
          else
            ($command.definition.arguments // {} | to_entries | map(.value))
          end
        )[]
      | [
          "ARGUMENT",
          $command.name,
          (.name // ""),
          (if .is_required then "1" else "0" end),
          (if .is_array then "1" else "0" end)
        ]
      | join("\u001f")
    '
  } 2>/dev/null) || return 1

  while IFS=$record_separator read -r kind command_name field_one field_two field_three field_four field_five; do
    [[ -n $kind ]] || continue

    case $kind in
      NAMESPACE)
        _drupalorg_append_unique_array_entry _DRUPALORG_TOP_LEVEL_MATCHES "${command_name}:"
        ;;
      COMMAND)
        _drupalorg_append_unique_array_entry _DRUPALORG_ALL_COMMANDS "$command_name"
        if [[ $command_name != *:* ]]; then
          _drupalorg_append_unique_array_entry _DRUPALORG_TOP_LEVEL_MATCHES "$command_name"
        fi
        ;;
      ALIAS)
        local alias_name=$command_name
        command_name=$field_one
        _DRUPALORG_ALIAS_TO_COMMAND[$alias_name]=$command_name
        _drupalorg_append_unique_array_entry _DRUPALORG_TOP_LEVEL_MATCHES "$alias_name"
        ;;
      OPTION)
        local option_key=$field_one
        local long_name=$field_two
        local shortcuts=$field_three
        local accepts_value=$field_four
        local is_multiple_flag=$field_five

        _DRUPALORG_COMMAND_OPTION_CANONICAL["$command_name:$long_name"]=$option_key
        _DRUPALORG_COMMAND_OPTION_TAKES_VALUE["$command_name:$option_key"]=$accepts_value
        _DRUPALORG_COMMAND_OPTION_REPEATABLE["$command_name:$option_key"]=$is_multiple_flag
        _drupalorg_append_unique_assoc_list _DRUPALORG_COMMAND_LONG_OPTIONS "$command_name" "$long_name"

        if [[ -n $shortcuts ]]; then
          local shortcut
          for shortcut in "${(@s:|:)shortcuts}"; do
            [[ -n $shortcut ]] || continue
            _DRUPALORG_COMMAND_OPTION_CANONICAL["$command_name:$shortcut"]=$option_key
            _drupalorg_append_unique_assoc_list _DRUPALORG_COMMAND_SHORT_OPTIONS "$command_name" "$shortcut"
          done
        fi
        ;;
      ARGUMENT)
        local argument_name=$field_one
        local is_required=$field_two
        local is_array=$field_three
        local record="${argument_name}|${is_required}|${is_array}"
        if [[ -n ${_DRUPALORG_COMMAND_ARGUMENTS[$command_name]-} ]]; then
          _DRUPALORG_COMMAND_ARGUMENTS[$command_name]+=$'\n'"$record"
        else
          _DRUPALORG_COMMAND_ARGUMENTS[$command_name]=$record
        fi
        ;;
    esac
  done <<< "$jq_output"

  _DRUPALORG_COMPLETION_CACHE_READY=1
}

_drupalorg_maybe_load_completion_cache() {
  emulate -L zsh
  setopt local_options no_sh_word_split typesetsilent

  local json_payload

  if [[ -n ${DRUPALORG_COMPLETION_RESET-} ]]; then
    _drupalorg_reset_completion_cache
    unset DRUPALORG_COMPLETION_RESET 2>/dev/null || true
  fi

  (( _DRUPALORG_COMPLETION_CACHE_READY )) && return 0
  (( $+commands[jq] )) || return 1

  json_payload=$(drupalorg list --format=json 2>/dev/null) || return 1
  [[ -n $json_payload ]] || return 1

  _drupalorg_load_json_completion_cache "$json_payload"
}

_drupalorg_resolve_command_name() {
  emulate -L zsh

  local command_name=$1
  print -r -- "${_DRUPALORG_ALIAS_TO_COMMAND[$command_name]:-$command_name}"
}

_drupalorg_collect_top_level_matches() {
  emulate -L zsh
  setopt local_options no_sh_word_split typesetsilent

  local prefix=$1
  local match
  local results=()

  for match in "${_DRUPALORG_TOP_LEVEL_MATCHES[@]}"; do
    [[ -z $prefix || $match == ${prefix}* ]] || continue
    results+=("$match")
  done

  print -r -- "${results[*]}"
}

_drupalorg_collect_subcommand_matches() {
  emulate -L zsh
  setopt local_options no_sh_word_split typesetsilent

  local prefix=$1
  local namespace=${prefix%%:*}
  local match
  local results=()

  for match in "${_DRUPALORG_ALL_COMMANDS[@]}"; do
    [[ $match == ${namespace}:* ]] || continue
    [[ -z $prefix || $match == ${prefix}* ]] || continue
    results+=("$match")
  done

  print -r -- "${results[*]}"
}

_drupalorg_collect_used_option_keys() {
  emulate -L zsh
  setopt local_options no_sh_word_split typesetsilent

  local command_name=$1
  shift

  local used_token
  local option_key
  local seen_keys=()

  for used_token in "$@"; do
    option_key=${_DRUPALORG_COMMAND_OPTION_CANONICAL["$command_name:$used_token"]-}
    [[ -n $option_key ]] || continue
    (( ${seen_keys[(I)$option_key]} )) || seen_keys+=("$option_key")
  done

  print -r -- "${seen_keys[*]}"
}

_drupalorg_collect_option_matches() {
  emulate -L zsh
  setopt local_options no_sh_word_split typesetsilent

  local command_name
  command_name=$(_drupalorg_resolve_command_name "$1")
  shift

  local prefix=$1
  shift

  local option_token option_key
  local candidate_options=()
  local used_keys=("${(@s: :)$(_drupalorg_collect_used_option_keys "$command_name" "$@")}")

  if [[ $prefix == --* ]]; then
    candidate_options=("${(@s: :)${_DRUPALORG_COMMAND_LONG_OPTIONS[$command_name]-}}")
  else
    candidate_options=("${(@s: :)${_DRUPALORG_COMMAND_SHORT_OPTIONS[$command_name]-}}")
  fi

  local results=()
  for option_token in "${candidate_options[@]}"; do
    [[ -z $prefix || $option_token == ${prefix}* ]] || continue

    option_key=${_DRUPALORG_COMMAND_OPTION_CANONICAL["$command_name:$option_token"]-}
    if [[ -n $option_key && ${_DRUPALORG_COMMAND_OPTION_REPEATABLE["$command_name:$option_key"]-0} != 1 ]]; then
      (( ${used_keys[(I)$option_key]} )) && continue
    fi

    results+=("$option_token")
  done

  print -r -- "${results[*]}"
}

_drupalorg_collect_argument_placeholders() {
  emulate -L zsh
  setopt local_options no_sh_word_split typesetsilent

  local command_name
  command_name=$(_drupalorg_resolve_command_name "$1")
  local position=$2
  local argument_records argument_record
  local -a placeholders=()

  [[ -n ${_DRUPALORG_COMMAND_ARGUMENTS[$command_name]-} ]] || return 0

  argument_records=("${(@f)${_DRUPALORG_COMMAND_ARGUMENTS[$command_name]}}")
  local total=${#argument_records[@]}
  (( total )) || return 0

  if (( position > total )); then
    local last_record=${argument_records[$total]}
    local last_name last_required last_array
    IFS='|' read -r last_name last_required last_array <<< "$last_record"
    if [[ $last_array == 1 ]]; then
      if [[ $last_required == 1 ]]; then
        print -r -- "<${last_name}>..."
      else
        print -r -- "[${last_name}]..."
      fi
    fi
    return 0
  fi

  argument_record=${argument_records[$position]}
  local argument_name is_required is_array
  IFS='|' read -r argument_name is_required is_array <<< "$argument_record"
  if [[ $is_required == 1 ]]; then
    placeholders+=("<${argument_name}>")
  else
    placeholders+=("[${argument_name}]")
  fi
  if [[ $is_array == 1 ]]; then
    placeholders[-1]+='...'
  fi

  print -r -- "${placeholders[*]}"
}

_drupalorg_option_requires_value() {
  emulate -L zsh

  local command_name
  command_name=$(_drupalorg_resolve_command_name "$1")
  local option_token=$2
  local option_key=${_DRUPALORG_COMMAND_OPTION_CANONICAL["$command_name:$option_token"]-}

  [[ -n $option_key && ${_DRUPALORG_COMMAND_OPTION_TAKES_VALUE["$command_name:$option_key"]-0} == 1 ]]
}

_drupalorg_fallback_raw_completion() {
  emulate -L zsh
  setopt local_options no_sh_word_split typesetsilent

  local cur line full ns i
  local -a top_matches sub_matches
  local -A seen_ns

  local seen_command=0
  for (( i = 2; i < CURRENT; i++ )); do
    if [[ ${words[i]} != -* ]]; then
      seen_command=1
      break
    fi
  done
  (( seen_command )) && return 1

  cur=${words[CURRENT]}

  while IFS= read -r line; do
    [[ -n $line ]] || continue
    full="${line%%[[:space:]]*}"

    if [[ $cur == *:* ]]; then
      ns="${cur%%:*}"
      [[ $full == ${ns}:* ]] || continue
      sub_matches+=("$full")
    else
      if [[ $full == *:* ]]; then
        ns="${full%%:*}"
        if [[ -z ${seen_ns[$ns]-} ]]; then
          seen_ns[$ns]=1
          top_matches+=("${ns}:")
        fi
      else
        top_matches+=("$full")
      fi
    fi
  done < <(drupalorg list --raw 2>/dev/null)

  if [[ $cur == *:* ]]; then
    (( ${#sub_matches[@]} )) || return 1
    compadd -Q -S '' -- "${sub_matches[@]}"
  else
    (( ${#top_matches[@]} )) || return 1
    compadd -Q -S '' -- "${top_matches[@]}"
  fi
}

_drupalorg() {
  emulate -L zsh
  setopt local_options no_sh_word_split typesetsilent

  local cur=${words[CURRENT]}
  local command_name=""
  local canonical_command=""
  local token
  local expecting_value=0
  local positional_count=0
  local i
  local -a used_options matches

  _drupalorg_maybe_load_completion_cache || {
    _drupalorg_fallback_raw_completion
    return $?
  }

  for (( i = 2; i < CURRENT; i++ )); do
    token=${words[i]}

    if (( expecting_value )); then
      expecting_value=0
      continue
    fi

    if [[ -z $command_name ]]; then
      [[ $token == -* ]] && continue
      command_name=$token
      canonical_command=$(_drupalorg_resolve_command_name "$command_name")
      continue
    fi

    if [[ $token == -* ]]; then
      used_options+=("$token")
      if _drupalorg_option_requires_value "$canonical_command" "$token"; then
        expecting_value=1
      fi
      continue
    fi

    positional_count=$(( positional_count + 1 ))
  done

  if [[ -z $command_name ]]; then
    if [[ $cur == *:* ]]; then
      matches=("${(@s: :)$(_drupalorg_collect_subcommand_matches "$cur")}")
    else
      matches=("${(@s: :)$(_drupalorg_collect_top_level_matches "$cur")}")
    fi

    (( ${#matches[@]} )) || return 1
    compadd -Q -S '' -- "${matches[@]}"
    return 0
  fi

  canonical_command=$(_drupalorg_resolve_command_name "$command_name")

  if (( CURRENT > 2 )) && _drupalorg_option_requires_value "$canonical_command" "${words[CURRENT-1]}"; then
    return 1
  fi

  if [[ $cur == -* ]]; then
    matches=("${(@s: :)$(_drupalorg_collect_option_matches "$canonical_command" "$cur" "${used_options[@]}")}")
    (( ${#matches[@]} )) || return 1
    compadd -Q -S '' -- "${matches[@]}"
    return 0
  fi

  matches=("${(@s: :)$(_drupalorg_collect_argument_placeholders "$canonical_command" $(( positional_count + 1 )))}")
  (( ${#matches[@]} )) || return 1
  compadd -Q -S '' -- "${matches[@]}"
}

(( $+functions[compdef] )) && compdef _drupalorg drupalorg
