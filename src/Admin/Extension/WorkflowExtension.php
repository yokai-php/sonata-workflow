<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Admin\Extension;

use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Workflow\Exception\InvalidArgumentException;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @author Yann Eugoné <eugone.yann@gmail.com>
 */
class WorkflowExtension extends AbstractAdminExtension
{
    private Registry $registry;
    private array $options;

    public function __construct(Registry $registry, array $options = [])
    {
        $this->registry = $registry;
        $this->configureOptions($resolver = new OptionsResolver());
        $this->options = $resolver->resolve($options);
    }

    /**
     * @inheritdoc
     */
    public function configureRoutes(AdminInterface $admin, RouteCollectionInterface $collection): void
    {
        $collection->add(
            'workflow_apply_transition',
            $admin->getRouterIdParameter() . '/workflow/transition/{transition}/apply'
        );
    }

    /**
     * @inheritdoc
     */
    public function alterNewInstance(AdminInterface $admin, $object): void
    {
        try {
            $workflow = $this->getWorkflow($object, $this->options['workflow_name']);
        } catch (InvalidArgumentException $exception) {
            return;
        }

        $workflow->getMarking($object);
    }

    /**
     * @inheritdoc
     */
    public function configureTabMenu(
        AdminInterface $admin,
        MenuItemInterface $menu,
        $action,
        ?AdminInterface $childAdmin = null
    ): void {
        if (null !== $childAdmin || !in_array($action, $this->options['render_actions'], true)) {
            return;
        }

        try {
            $subject = $admin->getSubject();
        } catch (\LogicException $exception) {
            return;
        }

        if (!$this->isGrantedView($admin, $subject)) {
            return;
        }

        try {
            $workflow = $this->getWorkflow($subject, $this->options['workflow_name']);
        } catch (InvalidArgumentException $exception) {
            return;
        }

        $transitions = $workflow->getEnabledTransitions($subject);

        if (count($transitions) === 0) {
            $this->noTransitions($menu, $admin);
        } else {
            $this->transitionsDropdown($menu, $admin, $transitions, $subject);
        }
    }

    /**
     * @inheritdoc
     */
    public function getAccessMapping(AdminInterface $admin): array
    {
        return [
            'viewTransitions' => $this->options['view_transitions_role'],
            'applyTransitions' => $this->options['apply_transitions_role'],
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function getWorkflow(object $subject, string|null $workflowName = null): WorkflowInterface
    {
        return $this->registry->get($subject, $workflowName);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'render_actions' => ['edit', 'show'],
                'workflow_name' => null,
                'no_transition_display' => false,
                'no_transition_label' => 'workflow_transitions_empty',
                'no_transition_icon' => 'fa fa-code-fork',
                'dropdown_transitions_label' => 'workflow_transitions',
                'dropdown_transitions_icon' => 'fa fa-code-fork',
                'transitions_default_icon' => null,
                'transitions_icons' => [],
                'view_transitions_role' => 'EDIT',
                'apply_transitions_role' => 'EDIT',
            ])
            ->setAllowedTypes('render_actions', ['string[]'])
            ->setAllowedTypes('workflow_name', ['string', 'null'])
            ->setAllowedTypes('no_transition_display', ['bool'])
            ->setAllowedTypes('no_transition_label', ['string'])
            ->setAllowedTypes('no_transition_icon', ['string'])
            ->setAllowedTypes('dropdown_transitions_label', ['string'])
            ->setAllowedTypes('dropdown_transitions_icon', ['string', 'null'])
            ->setAllowedTypes('transitions_default_icon', ['string', 'null'])
            ->setAllowedTypes('transitions_icons', ['array'])
            ->setAllowedTypes('view_transitions_role', ['string'])
            ->setAllowedTypes('apply_transitions_role', ['string'])
        ;
    }

    protected function noTransitions(MenuItemInterface $menu, AdminInterface $admin): void
    {
        if ($this->options['no_transition_display']) {
            $menu->addChild($this->options['no_transition_label'], [
                'uri' => '#',
                'attributes' => [
                    'icon' => $this->options['no_transition_icon'],
                ],
                'extras' => [
                    'translation_domain' => $admin->getTranslationDomain(),
                ],
            ]);
        }
    }

    /**
     * @param iterable&Transition[] $transitions
     */
    protected function transitionsDropdown(
        MenuItemInterface $menu,
        AdminInterface $admin,
        iterable $transitions,
        object $subject
    ): void {
        $workflowMenu = $menu->addChild($this->options['dropdown_transitions_label'], [
            'attributes' => [
                'dropdown' => true,
                'icon' => $this->options['dropdown_transitions_icon'],
            ],
            'extras' => [
                'translation_domain' => $admin->getTranslationDomain(),
            ],
        ]);

        foreach ($transitions as $transition) {
            $this->transitionsItem($workflowMenu, $admin, $transition, $subject);
        }
    }

    protected function transitionsItem(
        MenuItemInterface $menu,
        AdminInterface $admin,
        Transition $transition,
        object $subject
    ): void {
        $options = [
            'attributes' => [],
            'extras' => [
                'translation_domain' => $admin->getTranslationDomain(),
            ],
        ];

        if ($this->isGrantedApply($admin, $subject)) {
            $options['uri'] = $this->generateTransitionUri($admin, $transition, $subject);
        }

        if ($icon = $this->getTransitionIcon($transition)) {
            $options['attributes']['icon'] = $icon;
        }

        $menu->addChild(
            $admin->getLabelTranslatorStrategy()->getLabel($transition->getName(), 'workflow', 'transition'),
            $options
        );
    }

    protected function getTransitionIcon(Transition $transition): ?string
    {
        if (isset($this->options['transitions_icons'][$transition->getName()])) {
            return $this->options['transitions_icons'][$transition->getName()];
        }

        return $this->options['transitions_default_icon'];
    }

    protected function generateTransitionUri(AdminInterface $admin, Transition $transition, object $subject): string
    {
        return $admin->generateObjectUrl(
            'workflow_apply_transition',
            $subject,
            ['transition' => $transition->getName()]
        );
    }

    protected function isGrantedView(AdminInterface $admin, object $subject): bool
    {
        try {
            $admin->checkAccess('viewTransitions', $subject);
        } catch (AccessDeniedException $exception) {
            return false;
        }

        return true;
    }

    protected function isGrantedApply(AdminInterface $admin, object $subject): bool
    {
        try {
            $admin->checkAccess('applyTransitions', $subject);
        } catch (AccessDeniedException $exception) {
            return false;
        }

        return true;
    }
}
