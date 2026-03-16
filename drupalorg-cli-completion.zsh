#compdef drupalorg

_drupalorg() {
  local cur line full ns i
  local -a top_matches sub_matches
  local -A seen_ns

  # Only complete the first non-option positional argument.
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
        if [[ -z ${seen_ns[$ns]} ]]; then
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

compdef _drupalorg drupalorg
