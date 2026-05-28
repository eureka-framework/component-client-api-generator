<?php

/*
 * Copyright (c) velkuns
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator\Printer;

use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\Scalar;

class CustomStandard extends Standard
{
    private bool $isArrayMultiline;
    private bool $isMethodMultiline;
    private bool $isNewMultiline;

    /**
     * @param array{
     *     phpVersion?: PhpVersion,
     *     newline?: string,
     *     indent?: string,
     *     shortArraySyntax?: bool,
     *     arrayMultiline?: bool,
     *     methodMultiline?: bool,
     *     newMultiline?: bool,
     * } $options
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->isArrayMultiline  = $options['arrayMultiline'] ?? false;
        $this->isMethodMultiline = $options['methodMultiline'] ?? false;
        $this->isNewMultiline    = $options['newMultiline'] ?? false;
    }

    /**
     * @throws \Exception
     */
    protected function pScalar_String(Scalar\String_ $node): string
    {
        $kind     = $node->getAttribute('kind', Scalar\String_::KIND_SINGLE_QUOTED);
        $noEscape = $node->getAttribute('noEscape', false);

        if ($kind === Scalar\String_::KIND_DOUBLE_QUOTED && $noEscape === true) {
            return '"' . $node->value . '"';
        }

        return parent::pScalar_String($node);
    }
    protected function pExpr_Array(Expr\Array_ $node): string
    {
        if (!$this->isArrayMultiline) {
            return parent::pExpr_Array($node);
        }

        //~ When line is to long, force multiline
        $syntax = $node->getAttribute(
            'kind',
            $this->shortArraySyntax ? Expr\Array_::KIND_SHORT : Expr\Array_::KIND_LONG,
        );

        if ($syntax === Expr\Array_::KIND_SHORT) {
            $line = '[' . $this->pCommaSeparatedMultiline($node->items, true) . $this->nl . ']';
        } else {
            $line = 'array(' . $this->pCommaSeparatedMultiline($node->items, true) . ')';
        }

        return $line;
    }

    protected function pStmt_ClassMethod(Stmt\ClassMethod $node): string
    {
        if (!$this->isMethodMultiline) {
            return parent::pStmt_ClassMethod($node);
        }

        return $this->pAttrGroups($node->attrGroups)
            . $this->pModifiers($node->flags)
            . 'function ' . ($node->byRef ? '&' : '') . $node->name
            . '(' . $this->pCommaSeparatedMultiline($node->params, true) . ($node->params !== [] ? $this->nl : '') . ')'
            . (null !== $node->returnType ? ' : ' . $this->p($node->returnType) : '')
            . (null !== $node->stmts
                ? $this->nl . '{' . $this->pStmts($node->stmts) . ($node->stmts !== [] ? $this->nl : '') . '}'
                : ';');
    }

    protected function pExpr_New(Expr\New_ $node): string
    {
        if (!$this->isNewMultiline) {
            return parent::pExpr_New($node);
        }

        if ($node->class instanceof Stmt\Class_) {
            $args = $node->args !== [] ? '(' . $this->pCommaSeparatedMultiline($node->args, true) . $this->nl . ')' : '';
            return 'new ' . $this->pClassCommon($node->class, $args);
        }
        return 'new ' . $this->pNewOperand($node->class)
            . '(' . $this->pCommaSeparatedMultiline($node->args, true) . ($node->args !== [] ? $this->nl : '') . ')';
    }
}
