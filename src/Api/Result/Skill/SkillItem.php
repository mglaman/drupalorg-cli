<?php

namespace mglaman\DrupalOrg\Result\Skill;

final class SkillItem implements \JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $path,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'path' => $this->path,
        ];
    }
}
