<?php

namespace mglaman\DrupalOrg\Result\Skill;

use mglaman\DrupalOrg\Result\ResultInterface;

class SkillListResult implements ResultInterface
{
    /**
     * @param SkillItem[] $skills
     */
    public function __construct(
        public readonly array $skills,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'skills' => array_map(
                static fn(SkillItem $skill) => $skill->jsonSerialize(),
                $this->skills
            ),
        ];
    }
}
