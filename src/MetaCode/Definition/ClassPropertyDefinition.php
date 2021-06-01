<?php

namespace Reliese\MetaCode\Definition;

use Illuminate\Support\Str;
use Reliese\Blueprint\ColumnBlueprint;
use Reliese\Blueprint\ForeignKeyBlueprint;
use Reliese\MetaCode\Enum\InstanceEnum;
use Reliese\MetaCode\Enum\PhpTypeEnum;
use Reliese\MetaCode\Enum\VisibilityEnum;
use Reliese\MetaCode\Format\IndentationProvider;
use Reliese\MetaCode\Tool\ClassNameTool;

/**
 * Class ClassPropertyDefinition
 */
class ClassPropertyDefinition implements StatementDefinitionInterface
{
    protected ?ColumnBlueprint $columnBlueprint = null;
    /**
     * @var StatementDefinitionInterface|null
     */
    private ?StatementDefinitionInterface $defaultValueStatement = null;

    private ?ForeignKeyBlueprint $foreignKeyBlueprint = null;

    /**
     * @var InstanceEnum|null
     */
    private ?InstanceEnum $getterInstanceEnum = null;

    /**
     * @var StatementDefinitionInterface|null
     */
    private ?StatementDefinitionInterface $getterMethodBody;

    /**
     * @var VisibilityEnum|null
     */
    private ?VisibilityEnum $getterVisibilityEnum = null;

    /**
     * @var StatementDefinitionInterface[]
     */
    private array $additionalSetterOperations = [];

    /**
     * @var InstanceEnum|null
     */
    private ?InstanceEnum $instanceEnum;

    private bool $isAfterChangeObservable = false;

    private bool $isBeforeChangeObservable = false;

    /**
     * @var PhpTypeEnum
     */
    private PhpTypeEnum $phpTypeEnum;

    /**
     * @var InstanceEnum|null
     */
    private ?InstanceEnum $setterInstanceEnum = null;

    /**
     * @var VisibilityEnum|null
     */
    private ?VisibilityEnum $setterVisibilityEnum = null;

    /**
     * @var string
     * @var mixed|null
     */
    private mixed $value = null;

    /**
     * @var string
     */
    private string $variableName;

    /**
     * @var VisibilityEnum|null
     */
    private ?VisibilityEnum $visibilityEnum;

    /**
     * ClassPropertyDefinition constructor.
     *
     * @param string                            $variableName
     * @param PhpTypeEnum                       $phpTypeEnum
     * @param VisibilityEnum|null               $visibilityEnum
     * @param InstanceEnum|null                 $instanceEnum
     */
    public function __construct(string $variableName,
        PhpTypeEnum $phpTypeEnum,
        ?VisibilityEnum $visibilityEnum = null,
        ?InstanceEnum $instanceEnum = null,
    ) {
        $this->variableName = $variableName;
        $this->visibilityEnum = $visibilityEnum ?? VisibilityEnum::privateEnum();
        $this->instanceEnum = $instanceEnum ?? InstanceEnum::instanceEnum();
        $this->phpTypeEnum = $phpTypeEnum;
    }

    public function hasDefaultValueStatement(): bool {
        return $this->defaultValueStatement instanceof StatementDefinitionInterface;
    }

    public function getDefaultValueStatement(): StatementDefinitionInterface
    {
        return $this->defaultValueStatement;
    }

    public function setDefaultValueStatement(StatementDefinitionInterface $defaultValueStatement): static
    {
        $this->defaultValueStatement = $defaultValueStatement;
        return $this;
    }

    /**
     * @return InstanceEnum
     */
    public function getGetterInstanceEnum(): InstanceEnum
    {
        return $this->getterInstanceEnum;
    }

    /**
     * @return VisibilityEnum
     */
    public function getGetterVisibilityEnum(): VisibilityEnum
    {
        return $this->getterVisibilityEnum;
    }

    /**
     * @return bool
     */
    public function getIsAfterChangeObservable(): bool
    {
        return $this->isAfterChangeObservable;
    }

    /**
     * @return bool
     */
    public function getIsBeforeChangeObservable(): bool
    {
        return $this->isBeforeChangeObservable;
    }

    /**
     * @return PhpTypeEnum
     */
    public function getPhpTypeEnum(): PhpTypeEnum
    {
        return $this->phpTypeEnum;
    }

    /**
     * @return InstanceEnum
     */
    public function getSetterInstanceEnum(): InstanceEnum
    {
        return $this->setterInstanceEnum;
    }

    /**
     * @return ClassMethodDefinition
     */
    public function getSetterMethodDefinition(ClassDefinition $containingClass): ClassMethodDefinition
    {
        $param = new FunctionParameterDefinition(
            $this->getVariableName(),
            $this->getPhpTypeEnum()
        );
        $setter = new ClassMethodDefinition(
            $this->getSetterMethodName(),
            PhpTypeEnum::staticTypeEnum(),
            [
                $param,
            ],
            $this->getSetterVisibilityEnum(),
            $this->getSetterInstanceEnum(),
        );

        if ($this->getIsBeforeChangeObservable() ) {
            $setter->appendBodyStatement(
                new RawStatementDefinition(
                    \sprintf(
                        "\$this->raiseBeforeValueChange(static::%s, \$this->%s, \$%s);\n",
                        ClassPropertyDefinition::getPropertyNameConstantName($this->getVariableName()),
                        $this->getVariableName(),
                        $param->getParameterName(),
                    )
                )
            );
        }

        $setter->appendBodyStatement(new RawStatementDefinition(\sprintf("\$this->%s = $%s;\n",
                $this->getVariableName(),
                $param->getParameterName())));

        foreach ($this->additionalSetterOperations as $additionalSetterOperation) {
            $setter->appendBodyStatement($additionalSetterOperation);
        }

        if ($this->getIsAfterChangeObservable() && $containingClass->hasTrait('AfterValueChangeObservableTrait')) {
            $setter->appendBodyStatement(new RawStatementDefinition(\sprintf("\$this->raiseAfterValueChange(static::%s, \$this->%s);\n",
                        ClassPropertyDefinition::getPropertyNameConstantName($this->getVariableName()),
                        $this->getVariableName())));
        }

        $setter->appendBodyStatement(new RawStatementDefinition('return $this;'));

        return $setter;
    }

    /**
     * @return VisibilityEnum
     */
    public function getSetterVisibilityEnum(): VisibilityEnum
    {
        return $this->setterVisibilityEnum;
    }

    /**
     * @return string
     */
    public function getVariableName(): string
    {
        return $this->variableName;
    }

    /**
     * @return VisibilityEnum
     */
    public function getVisibilityEnum(): VisibilityEnum
    {
        return $this->visibilityEnum;
    }

    /**
     * @return bool
     */
    public function hasGetter(): bool
    {
        return !is_null($this->getterVisibilityEnum);
    }

    /**
     * @return bool
     */
    public function hasSetter(): bool
    {
        return !is_null($this->setterVisibilityEnum);
    }

    /**
     * @param bool $isAfterChangeObservable
     *
     * @return ClassPropertyDefinition
     */
    public function setIsAfterChangeObservable(bool $isAfterChangeObservable): ClassPropertyDefinition
    {
        $this->isAfterChangeObservable = $isAfterChangeObservable;
        return $this;
    }

    /**
     * @param bool $isBeforeChangeObservable
     *
     * @return ClassPropertyDefinition
     */
    public function setIsBeforeChangeObservable(bool $isBeforeChangeObservable): ClassPropertyDefinition
    {
        $this->isBeforeChangeObservable = $isBeforeChangeObservable;
        return $this;
    }

    /**
     * @param VisibilityEnum|null               $getterVisibilityEnum
     * @param InstanceEnum|null                 $getterInstanceEnum
     * @param StatementDefinitionInterface|null $statementDefinitionInterface
     *
     * @return $this
     */
    public function withGetter(
        ?VisibilityEnum $getterVisibilityEnum = null,
        ?InstanceEnum $getterInstanceEnum = null,
        ?StatementDefinitionInterface $statementDefinitionInterface = null
    ): ClassPropertyDefinition {
        $this->getterVisibilityEnum = $getterVisibilityEnum ?? VisibilityEnum::publicEnum();
        $this->getterInstanceEnum = $getterInstanceEnum ?? InstanceEnum::instanceEnum();
        $this->getterMethodBody = $statementDefinitionInterface;
        return $this;
    }

    /**
     * @param VisibilityEnum|null $setterVisibilityEnum
     * @param InstanceEnum|null   $setterInstanceEnum
     *
     * @return $this
     */
    public function withSetter(?VisibilityEnum $setterVisibilityEnum = null,
        ?InstanceEnum $setterInstanceEnum = null): ClassPropertyDefinition
    {
        $this->setterVisibilityEnum = $setterVisibilityEnum ?? VisibilityEnum::publicEnum();
        $this->setterInstanceEnum = $setterInstanceEnum ?? InstanceEnum::instanceEnum();
        return $this;
    }

    public function getSetterMethodName(): string
    {
        return 'set' . Str::studly($this->getVariableName());
    }

    /**
     * @param StatementDefinitionInterface $statementDefinition
     *
     * @return $this
     */
    public function addAdditionalSetterOperation(StatementDefinitionInterface $statementDefinition): static
    {
        $this->additionalSetterOperations[] = $statementDefinition;
        return $this;
    }

    /**
     * @param ClassDefinition $classDefinition
     *
     * @return ClassMethodDefinition
     */
    public function getGetterMethodDefinition(ClassDefinition $classDefinition) : ClassMethodDefinition
    {
        return $this->getterMethodDefinition ??= $this->defaultGetterMethodDefinition();
    }

    /**
     * @param ColumnBlueprint|null $columnBlueprint
     *
     * @return ClassPropertyDefinition
     */
    public function setColumnBlueprint(?ColumnBlueprint $columnBlueprint): ClassPropertyDefinition
    {
        $this->columnBlueprint = $columnBlueprint;
        return $this;
    }

    /**
     * @return ColumnBlueprint
     */
    public function getColumnBlueprint(): ColumnBlueprint
    {
        return $this->columnBlueprint;
    }

    /**
     * @return bool
     */
    public function hasColumnBlueprint(): bool
    {
        return $this->columnBlueprint instanceof ColumnBlueprint;
    }

    /**
     * @param ForeignKeyBlueprint|null $foreignKeyBlueprint
     *
     * @return ClassPropertyDefinition
     */
    public function setForeignKeyBlueprint(?ForeignKeyBlueprint $foreignKeyBlueprint): ClassPropertyDefinition
    {
        $this->foreignKeyBlueprint = $foreignKeyBlueprint;
        return $this;
    }

    /**
     * @return ForeignKeyBlueprint
     */
    public function getForeignKeyBlueprint(): ForeignKeyBlueprint
    {
        return $this->foreignKeyBlueprint;
    }

    /**
     * @return bool
     */
    public function hasForeignKeyBlueprint(): bool
    {
        return $this->foreignKeyBlueprint instanceof ForeignKeyBlueprint;
    }

    /**
     * @return ClassMethodDefinition
     */
    protected function defaultGetterMethodDefinition(): ClassMethodDefinition
    {
        $getterFunctionName = $this->getGetterMethodName();
        $getterFunctionType = $this->getPhpTypeEnum();

        $classMethod = new ClassMethodDefinition($getterFunctionName, $getterFunctionType);

        if ($this->getterMethodBody instanceof StatementDefinitionInterface) {
            $classMethod->appendBodyStatement($this->getterMethodBody);
        } else {
            $classMethod->appendBodyStatement(new RawStatementDefinition('return $this->' . $this->getVariableName() . ';'));
        }

        return $classMethod;
    }

    public function getGetterMethodName(): string
    {
        return ClassNameTool::variableNameToGetterName($this->getVariableName());
    }

    public static function getPropertyNameConstantName(string $propertyName): string
    {
        return "".ClassNameTool::identifierNameToConstantName($propertyName)."_PROPERTY";
    }

    public function toPhpCode(IndentationProvider $indentationProvider): string
    {
        $defaultValueString = '';
        if ($this->hasDefaultValueStatement()) {
            $defaultValueString = ' = '. $this->getDefaultValueStatement()->toPhpCode(IndentationProvider::NoIndentation());
        } elseif ($this->getPhpTypeEnum()->isNullable()) {
            $defaultValueString = ' = null';
        }

        return $indentationProvider->getIndentation()
            . $this->getVisibilityEnum()->toReservedWord()
            . ' '
            . $this->getPhpTypeEnum()->toTypeHint()
            . ' $'
            . $this->getVariableName()
            . $defaultValueString
            . ';';
    }
}