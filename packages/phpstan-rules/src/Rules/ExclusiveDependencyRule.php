<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\PackageBuilder\Matcher\ArrayStringAndFnMatcher;
use Symplify\PackageBuilder\ValueObject\MethodName;
use Symplify\RuleDocGenerator\Contract\ConfigurableRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\ExclusiveDependencyRule\ExclusiveDependencyRuleTest
 */
final class ExclusiveDependencyRule extends AbstractSymplifyRule implements ConfigurableRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = '"%s" dependency is allowed only in "%s" types';

    /**
     * @param array<string, string[]> $allowedExclusiveDependencyInTypes
     */
    public function __construct(
        private SimpleNameResolver $simpleNameResolver,
        private ArrayStringAndFnMatcher $arrayStringAndFnMatcher,
        private array $allowedExclusiveDependencyInTypes
    ) {
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if (! $this->simpleNameResolver->isName($node->name, MethodName::CONSTRUCTOR)) {
            return [];
        }

        $className = $this->simpleNameResolver->getClassNameFromScope($scope);
        if ($className === null) {
            return [];
        }

        $paramTypes = $this->resolveParamTypes($node);

        foreach ($paramTypes as $paramType) {
            foreach ($this->allowedExclusiveDependencyInTypes as $dependencyType => $allowedTypes) {
                if ($this->isExclusiveMatchingDependency($paramType, $dependencyType, $className, $allowedTypes)) {
                    continue;
                }

                $errorMessage = sprintf(self::ERROR_MESSAGE, $dependencyType, implode('", "', $allowedTypes));
                return [$errorMessage];
            }
        }

        return [];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Dependency of specific type can be used only in specific class types', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
final class CheckboxController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
final class CheckboxRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }
}
CODE_SAMPLE
                ,
                [
                    'allowedExclusiveDependencyInTypes' => [
                        'Doctrine\ORM\EntityManager' => ['*Repository'],
                        'Doctrine\ORM\EntityManagerInterface' => ['*Repository'],
                    ],
                ]
            ),
        ]);
    }

    /**
     * @param string[] $allowedTypes
     */
    private function isExclusiveMatchingDependency(
        string $paramType,
        string $dependencyType,
        string $className,
        array $allowedTypes
    ): bool {
        if (! $this->arrayStringAndFnMatcher->isMatch($paramType, [$dependencyType])) {
            return true;
        }

        // instancef of but with static reflection
        $classObjectType = new ObjectType($className);
        foreach ($allowedTypes as $allowedType) {
            if ($classObjectType->isInstanceOf($allowedType)->yes()) {
                return true;
            }
        }

        return $this->arrayStringAndFnMatcher->isMatch($className, $allowedTypes);
    }

    /**
     * @return string[]
     */
    private function resolveParamTypes(ClassMethod $classMethod): array
    {
        $paramTypes = [];

        foreach ($classMethod->params as $param) {
            /** @var Param $param */
            if ($param->type === null) {
                continue;
            }

            $paramType = $this->simpleNameResolver->getName($param->type);
            if ($paramType === null) {
                continue;
            }

            $paramTypes[] = $paramType;
        }

        return $paramTypes;
    }
}
