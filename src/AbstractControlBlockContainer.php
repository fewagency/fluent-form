<?php
namespace FewAgency\FluentForm;

use FewAgency\FluentForm\Support\ContainingBlockContainersTrait;
use FewAgency\FluentForm\Support\ContainingBlocksTrait;
use FewAgency\FluentForm\Support\MapCollection;
use FewAgency\FluentHtml\FluentHtmlElement;
use FewAgency\FluentForm\Support\FormElementContract;
use FewAgency\FluentForm\Support\FormElementTrait;
use Illuminate\Contracts\Support\MessageProvider;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

abstract class AbstractControlBlockContainer extends FluentHtmlElement implements FormElementContract
{
    use FormElementTrait {
        FormElementTrait::isInline as isAncestorInline;
        FormElementTrait::isAligned as isAncestorAligned;
    }
    use ContainingBlocksTrait, ContainingBlockContainersTrait;

    /**
     * @var string css classname to put on block containers
     */
    protected $form_block_container_class = 'form-block-container';

    /**
     * @var string css classname to put on aligned block containers
     */
    protected $form_block_container_aligned_class = "form-block-container--aligned";

    /**
     * @var string css classname to put on inline block containers
     */
    protected $form_block_container_inline_class = "form-block-container--inline";

    protected $is_aligned;
    protected $alignment_classes;
    protected $alignment_classes_default = [
        1 => 'form-block__align1',
        2 => 'form-block__align2',
        3 => 'form-block__align3',
    ];
    protected $alignment_offset_classes;
    protected $alignment_offset_classes_default = [2 => 'form-block-container__align2--offset', 3 => ''];

    /**
     * The values and selected options for inputs in this level of the form.
     * @var MapCollection
     */
    private $value_maps;

    /**
     * The labels for controls in this level of the form.
     * @var MapCollection
     */
    private $label_maps;

    /**
     * Error messages for this level of the form.
     * @var MessageBag
     */
    private $error_messages;

    /**
     * Warning messages for this level of the form.
     * @var MessageBag
     */
    private $warning_messages;

    /**
     * Success controls in this level of the form.
     * @var MapCollection
     */
    private $success_maps;

    /**
     * Disabled controls in this level of the form.
     * @var MapCollection
     */
    private $disabled_maps;

    /**
     * Readonly controls in this level of the form.
     * @var MapCollection
     */
    private $readonly_maps;

    /**
     * Required controls in this level of the form.
     * @var MapCollection
     */
    private $required_maps;

    /**
     * AbstractControlBlock elements in this container.
     * @var Collection
     */
    private $form_block_elements;

    /**
     * AbstractControlBlockContainer element in this container.
     * @var Collection
     */
    private $form_block_container_elements;

    /**
     * @var bool|callable|null indicating if the block container's content is inline
     */
    private $is_inline;

    public function __construct()
    {
        parent::__construct();
        $this->value_maps = new MapCollection();
        $this->label_maps = new MapCollection();
        $this->form_block_elements = new Collection();
        $this->form_block_container_elements = new Collection();
        $this->error_messages = new MessageBag();
        $this->warning_messages = new MessageBag();
        $this->success_maps = new MapCollection();
        $this->disabled_maps = new MapCollection();
        $this->readonly_maps = new MapCollection();
        $this->required_maps = new MapCollection();
        $this->withClass([
            $this->form_block_container_class,
            $this->form_block_container_aligned_class => function () {
                return $this->isAligned();
            },
            $this->form_block_container_inline_class => function () {
                return $this->isInline();
            }
        ]);
        $this->withContent(function () {
            //This prints blocks' description elements at top of the container under certain conditions
            if ($this->isInline()) {
                $container = $this->getControlBlockContainer();
                if (empty($container) or !$container->isInline()) {
                    return $this->pullSubBlocksDescriptionElements();
                }
            }

            return null;
        });
    }


    /**
     * Add a hidden input to the form
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function withHiddenInput($name, $value = null)
    {
        $this->withContent($this->createInstanceOf('HiddenInputElement', [$name, $value]));

        return $this;
    }

    /**
     * Set a value container for populating inputs.
     * @param array|object|Arrayable $map,... key-value implementations
     * @return $this
     */
    public function withValues($map)
    {
        $this->value_maps->prependMaps(func_get_args());

        return $this;
    }

    /**
     * Get an input's value or selected option.
     * @param string $key in dot-notation
     * @return mixed|null
     */
    public function getValue($key)
    {
        return $this->evaluate($this->value_maps)->firstValue($key, function () use ($key) {
            return $this->getValueFromAncestor($key);
        });
    }

    /**
     * Add maps of labels, keyed by control name.
     *
     * @param  array|Arrayable $map,...
     * @return $this
     */
    public function withLabels($map)
    {
        $this->label_maps->prependMaps(func_get_args());

        return $this;
    }

    /**
     * Get a label from a key.
     * @param string $key in dot-notation
     * @return mixed|null
     */
    public function getLabel($key)
    {
        return $this->evaluate($this->label_maps)->firstValue($key, function () use ($key) {
            return $this->getLabelFromAncestor($key);
        });
    }

    /**
     * Merge a new array of messages into the error messages.
     *
     * @param  MessageProvider|array $messages keyed by control name
     * @return $this
     */
    public function withErrors($messages)
    {
        if ($messages instanceof ViewErrorBag) {
            $messages = $messages->getMessageBag();
        }
        $this->error_messages->merge($messages);

        return $this;
    }

    /**
     * Get the error messages for a form control.
     *
     * @param string $key
     * @return array of message strings
     */
    public function getErrors($key)
    {
        return array_merge($this->error_messages->get($key), $this->getErrorsFromAncestor($key));
    }

    /**
     * Merge a new array of messages into the warning messages.
     *
     * @param  MessageProvider|array $messages keyed by control name
     * @return $this
     */
    public function withWarnings($messages)
    {
        if ($messages instanceof ViewErrorBag) {
            $messages = $messages->getMessageBag();
        }
        $this->warning_messages->merge($messages);

        return $this;
    }

    /**
     * Get the warning messages for a form control.
     *
     * @param string $key
     * @return array of message strings
     */
    public function getWarnings($key)
    {
        return array_merge($this->warning_messages->get($key), $this->getWarningsFromAncestor($key));
    }

    /**
     * Add control names that have success status.
     * @param string|callable|array|Arrayable $map,... key-boolean map(s) or string(s) of success form control names
     * @return $this
     */
    public function withSuccess($map)
    {
        $this->success_maps->prependMaps(func_get_args());

        return $this;
    }

    /**
     * Find out if a control has success state.
     * @param string $key
     * @return bool
     */
    public function hasSuccess($key)
    {
        return $this->evaluate($this->success_maps)->firstBoolean($key, function () use ($key) {
            return $this->hasSuccessFromAncestor($key);
        });
    }

    /**
     * Add control names that are disabled.
     * @param string|callable|array|Arrayable $map,... key-boolean map(s) or string(s) of disabled form control names
     * @return $this
     */
    public function withDisabled($map)
    {
        $this->disabled_maps->prependMaps(func_get_args());

        return $this;
    }

    /**
     * Find out if a control is disabled.
     * @param string $key
     * @return bool
     */
    public function isDisabled($key)
    {
        return $this->evaluate($this->disabled_maps)->firstBoolean($key, function () use ($key) {
            return $this->isDisabledFromAncestor($key);
        });
    }

    /**
     * Add control names that are readonly.
     * @param string|callable|array|Arrayable $map,... key-boolean map(s) or string(s) of readonly form control names
     * @return $this
     */
    public function withReadonly($map)
    {
        $this->readonly_maps->prependMaps(func_get_args());

        return $this;
    }

    /**
     * Find out if a control is readonly.
     * @param string $key
     * @return bool
     */
    public function isReadonly($key)
    {
        return $this->evaluate($this->readonly_maps)->firstBoolean($key, function () use ($key) {
            return $this->isReadonlyFromAncestor($key);
        });
    }

    /**
     * Add control names that are required.
     * @param string|callable|array|Arrayable $map,... key-boolean map(s) or string(s) of required form control names
     * @return $this
     */
    public function withRequired($map)
    {
        $this->required_maps->prependMaps(func_get_args());

        return $this;
    }

    /**
     * Find out if a control is required.
     * @param string $key
     * @return bool
     */
    public function isRequired($key)
    {
        return $this->evaluate($this->required_maps)->firstBoolean($key, function () use ($key) {
            return $this->isRequiredFromAncestor($key);
        });
    }

    /**
     * Set custom classes for horizontally aligned inputs in this container.
     * @param string|array $classes1
     * @param string|array $classes2
     * @param string|array $classes3
     * @param string|array $offset_classes2
     * @param string|array|null $offset_classes3
     */
    function withAlignmentClasses($classes1, $classes2, $classes3, $offset_classes2, $offset_classes3 = null)
    {
        $this->alignment_classes = [1 => $classes1, 2 => $classes2, 3 => $classes3];
        $this->alignment_offset_classes = [2 => $offset_classes2, 3 => $offset_classes3];
    }

    /**
     * Get the classes for an alignment element.
     * @param $number 1: label, 2: input, or 3: description
     * @param bool $with_offset Switch to include offset classes (if preceding element was left blank)
     * @return array of class names
     */
    public function getAlignmentClasses($number, $with_offset = false)
    {
        if (!empty($this->alignment_classes)) {
            $alignment_classes = $this->alignment_classes;
            $alignment_offset_classes = $this->alignment_offset_classes;
        } elseif ($ancestor = $this->getControlBlockContainer()) {
            return $ancestor->getAlignmentClasses($number, $with_offset);
        } else {
            $alignment_classes = $this->alignment_classes_default;
            $alignment_offset_classes = $this->alignment_offset_classes_default;
        }
        $classes = (array)$alignment_classes[$number];
        if ($with_offset) {
            $classes = array_merge($classes, (array)$alignment_offset_classes[$number]);
        }

        return $classes;
    }

    /**
     * Make control blocks in this container display horizontally aligned.
     * @param bool|callable $align
     * @return $this
     */
    public function aligned($align = true)
    {
        $this->is_aligned = $align;

        return $this;
    }

    /**
     * Check if the control blocks in this container are set to display horizontally aligned.
     * @return bool
     */
    public function isAligned()
    {
        $aligned = $this->evaluate($this->is_aligned);
        if (!isset($aligned)) {
            $aligned = $this->isAncestorAligned();
        }

        return (bool)$aligned;
    }

    /**
     * Make contents in this form block container display inline.
     * @param bool|callable $inline
     * @return $this
     */
    public function inline($inline = true)
    {
        $this->is_inline = $inline;

        return $this;
    }

    /**
     * Check if the contents of this container are set to display inline.
     * @return bool
     */
    public function isInline()
    {
        $inline = $this->evaluate($this->is_inline);
        if (!isset($inline)) {
            $inline = $this->isAncestorInline();
        }

        return (bool)$inline;
    }

    /**
     * Pull all contained form-blocks' descriptions for display outside their blocks.
     * @return Collection
     */
    protected function pullSubBlocksDescriptionElements()
    {
        return $this->form_block_elements->map(function (AbstractControlBlock $block) {
            return $block->pullDescriptionElement();
        })->merge($this->form_block_container_elements->map(function (AbstractControlBlockContainer $container) {
            return $container->pullSubBlocksDescriptionElements();
        }));
    }

    /**
     * Generate a new control-block.
     * @param string $type
     * @param array $parameters
     * @return AbstractControlBlock
     */
    public function createControlBlock($type, $parameters = [])
    {
        try {
            $block = $this->createInstanceOf($type . 'Block', $parameters);
        } catch (\Exception $e) {
            $block = $this->createInstanceOf('InputBlock', array_merge($parameters, [$type]));
        }

        return $block;
    }

    /**
     * Overridden to make sure any inserted control blocks and sub-containers are registered with this container
     * @inheritdoc
     */
    protected function prepareContentsForInsertion($html_contents)
    {
        return parent::prepareContentsForInsertion(func_get_args())->each(function ($item) {
            if ($item instanceof AbstractControlBlock) {
                $this->form_block_elements->push($item);
            } elseif ($item instanceof AbstractControlBlockContainer) {
                $this->form_block_container_elements->push($item);
            }
        });
    }


}