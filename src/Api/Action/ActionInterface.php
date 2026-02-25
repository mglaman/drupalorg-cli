<?php

namespace mglaman\DrupalOrg\Action;

/**
 * Contract for Action classes.
 *
 * Actions encapsulate discrete units of business logic for interacting with
 * the Drupal.org API. They are channel-agnostic: the same Action can be
 * invoked by a Symfony Console command, an MCP server, or any other consumer.
 *
 * Implementations must define __invoke() with domain-specific parameters
 * returning a concrete ResultInterface implementation.
 */
interface ActionInterface
{
}
