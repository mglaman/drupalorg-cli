<?php

namespace mglaman\DrupalOrg\Action\Skill;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Result\Skill\SkillItem;
use mglaman\DrupalOrg\Result\Skill\SkillListResult;

/**
 * Lists the skills bundled in skill-data/, sorted by name.
 *
 * Each skill is a directory containing a SKILL.md whose YAML frontmatter
 * carries a name and a description. The frontmatter is parsed by hand so
 * the phar does not need a YAML dependency.
 */
class ListSkillsAction implements ActionInterface
{
    public const DEFAULT_SKILLS_ROOT = __DIR__ . '/../../../../skill-data';

    private readonly string $skillsRoot;

    public function __construct(string $skillsRoot = self::DEFAULT_SKILLS_ROOT)
    {
        // realpath() cannot resolve phar:// paths, so keep the raw path there.
        $resolved = realpath($skillsRoot);
        $this->skillsRoot = $resolved === false ? $skillsRoot : $resolved;
    }

    public function __invoke(): SkillListResult
    {
        if (!is_dir($this->skillsRoot)) {
            return new SkillListResult(skills: []);
        }

        $skills = [];
        foreach (new \DirectoryIterator($this->skillsRoot) as $dir) {
            if ($dir->isDot() || !$dir->isDir()) {
                continue;
            }
            $skillFile = $dir->getPathname() . '/SKILL.md';
            if (!is_file($skillFile)) {
                continue;
            }
            $content = file_get_contents($skillFile);
            if ($content === false) {
                continue;
            }
            $frontmatter = self::parseFrontmatter($content);
            $skills[] = new SkillItem(
                name: $frontmatter['name'] ?? $dir->getFilename(),
                description: $frontmatter['description'] ?? '',
                path: $skillFile,
            );
        }

        usort($skills, static fn(SkillItem $a, SkillItem $b) => strcmp($a->name, $b->name));

        return new SkillListResult(skills: $skills);
    }

    /**
     * Reads top-level scalar keys from a SKILL.md frontmatter block.
     *
     * Supports plain `key: value` pairs and block scalars (`key: >` or
     * `key: |`) whose indented continuation lines are joined into one line.
     *
     * @return array<string, string>
     */
    private static function parseFrontmatter(string $content): array
    {
        if (preg_match('/\A---\R(.*?)\R---(?:\R|\z)/s', $content, $matches) !== 1) {
            return [];
        }

        $values = [];
        $currentKey = null;
        $lines = preg_split('/\R/', $matches[1]);
        if ($lines === false) {
            return [];
        }
        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', $line, $pair) === 1) {
                $currentKey = $pair[1];
                $value = trim($pair[2]);
                $values[$currentKey] = in_array($value, ['>', '|', '>-', '|-'], true) ? '' : $value;
                continue;
            }
            if ($currentKey !== null && trim($line) !== '') {
                $values[$currentKey] = trim($values[$currentKey] . ' ' . trim($line));
            }
        }

        return $values;
    }
}
