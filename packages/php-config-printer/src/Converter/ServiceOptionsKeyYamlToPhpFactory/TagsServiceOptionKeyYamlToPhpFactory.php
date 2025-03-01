<?php

declare(strict_types=1);

namespace Symplify\PhpConfigPrinter\Converter\ServiceOptionsKeyYamlToPhpFactory;

use PhpParser\BuilderHelpers;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use Symplify\PhpConfigPrinter\Contract\Converter\ServiceOptionsKeyYamlToPhpFactoryInterface;
use Symplify\PhpConfigPrinter\NodeFactory\ArgsNodeFactory;
use Symplify\PhpConfigPrinter\ValueObject\YamlServiceKey;

final class TagsServiceOptionKeyYamlToPhpFactory implements ServiceOptionsKeyYamlToPhpFactoryInterface
{
    /**
     * @var string
     */
    private const TAG = 'tag';

    public function __construct(
        private ArgsNodeFactory $argsNodeFactory
    ) {
    }

    public function decorateServiceMethodCall($key, $yamlLines, $values, MethodCall $methodCall): MethodCall
    {
        /** @var mixed[] $yamlLines */
        if (count($yamlLines) === 1 && is_string($yamlLines[0])) {
            $string = new String_($yamlLines[0]);
            return new MethodCall($methodCall, self::TAG, [new Arg($string)]);
        }

        foreach ($yamlLines as $yamlLine) {
            $args = [];
            foreach ($yamlLine as $singleNestedKey => $singleNestedValue) {
                if ($singleNestedKey === 'name') {
                    $args[] = new Arg(BuilderHelpers::normalizeValue($singleNestedValue));
                    unset($yamlLine[$singleNestedKey]);
                }
            }

            $restArgs = $this->argsNodeFactory->createFromValuesAndWrapInArray($yamlLine);

            $args = array_merge($args, $restArgs);
            $methodCall = new MethodCall($methodCall, self::TAG, $args);
        }

        return $methodCall;
    }

    public function isMatch(mixed $key, mixed $values): bool
    {
        return $key === YamlServiceKey::TAGS;
    }
}
