<?php

namespace mglaman\DrupalOrg\Result;

/**
 * Contract for Result DTOs.
 *
 * Results are structured value objects returned by Action classes. They
 * implement \JsonSerializable so they can be serialized for JSON output
 * (e.g. --format=json) or consumed by non-CLI channels such as an MCP server.
 */
interface ResultInterface extends \JsonSerializable
{
}
