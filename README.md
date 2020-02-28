Yokai Sonata Workflow
=====================

[![Latest Stable Version](https://poser.pugx.org/yokai/sonata-workflow/v/stable)](https://packagist.org/packages/yokai/sonata-workflow)
[![Latest Unstable Version](https://poser.pugx.org/yokai/sonata-workflow/v/unstable)](https://packagist.org/packages/yokai/sonata-workflow)
[![Total Downloads](https://poser.pugx.org/yokai/sonata-workflow/downloads)](https://packagist.org/packages/yokai/sonata-workflow)
[![License](https://poser.pugx.org/yokai/sonata-workflow/license)](https://packagist.org/packages/yokai/sonata-workflow)

[![Build Status](https://api.travis-ci.org/yokai-php/sonata-workflow.png?branch=master)](https://travis-ci.org/yokai-php/sonata-workflow)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yokai-php/sonata-workflow/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yokai-php/sonata-workflow/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yokai-php/sonata-workflow/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yokai-php/sonata-workflow/?branch=master)


Introduction
------------

This library add Symfony Workflow component integration within Sonata Admin.

### Features

- add a menu dropdown to your admin detail pages on which you have buttons to apply available transitions
- ship a controller to apply transition
- allow to hook into the apply transition process to show an intermediate page

### Code

- a Sonata Admin [Extension](https://sonata-project.org/bundles/admin/master/doc/reference/extensions.html) :
  [WorkflowExtension](src/Admin/Extension/WorkflowExtension.php)
- a Controller trait :
  [WorkflowControllerTrait](src/Controller/WorkflowControllerTrait.php)
- a Controller :
  [WorkflowController](src/Controller/WorkflowController.php)


Installation
------------

``` bash
$ composer require yokai/sonata-workflow
```

Configuration
-------------

Let say that you have an entity named `PullRequest` that is under workflow and for which you have an admin.

#### symfony/workflow <4.3
```yaml
# config/packages/workflow.yml
framework:
    workflows:
        pull_request:
            type: state_machine
            marking_store:
                type: single_state
                arguments:
                    - status
            supports:
                - App\Entity\PullRequest
            places:
                - opened
                - pending_review
                - merged
                - closed
            initial_place: opened
            transitions:
                start_review:
                    from: opened
                    to:   pending_review
                merge:
                    from: pending_review
                    to:   merged
                close:
                    from: pending_review
                    to:   closed
```

#### symfony/workflow ^4.3|^5.0
```yaml
# config/packages/workflow.yml
framework:
    workflows:
        pull_request:
            type: state_machine
            marking_store:
                type: state_machine
                property: status
            supports:
                - App\Entity\PullRequest
            places:
                - opened
                - pending_review
                - merged
                - closed
            initial_marking:
                - opened
            transitions:
                start_review:
                    from: opened
                    to:   pending_review
                merge:
                    from: pending_review
                    to:   merged
                close:
                    from: pending_review
                    to:   closed
```

### One extension for everything

The extension is usable for many entities and with no configuration.

You only need to create a service for it, configure the controller that will handle the transition action
and configure on which admin you want it available.

For instance :

```yaml
# config/packages/sonata_admin.yml
services:
    admin.pull_request:
        class: App\Admin\PullRequestAdmin
        public: true
        arguments: [~, App\Entity\PullRequest, Yokai\SonataWorkflow\Controller\WorkflowController]
        tags:
            - { name: 'sonata.admin', manager_type: orm, label: PullRequest }
    admin.extension.workflow:
        class: Yokai\SonataWorkflow\Admin\Extension\WorkflowExtension
        public: true
        arguments:
            - '@workflow.registry'
    Yokai\SonataWorkflow\Controller\WorkflowController:
        autowire: true
        tags: ['controller.service_arguments']

sonata_admin:
    extensions:
        admin.extension.workflow:
            admins:
                - admin.pull_request
```

> **note**: You may noticed that we also registered the controller
`Yokai\SonataWorkflow\Controller\WorkflowController` as a service.
It is important, because it needs the workflow registry service to work.

### More specific extension per admin

But the extension accepts many options if you wish to customize the behavior.

For instance :

```yaml
# config/packages/sonata_admin.yml
services:
    admin.pull_request:
        class: App\Admin\PullRequestAdmin
        public: true
        arguments: [~, App\Entity\PullRequest, 'Yokai\SonataWorkflow\Controller\WorkflowController']
        tags:
            - { name: 'sonata.admin', manager_type: orm, label: PullRequest }
    admin.extension.pull_request_workflow:
        class: Yokai\SonataWorkflow\Admin\Extension\WorkflowExtension
        public: true
        arguments:
            - '@workflow.registry'
            - render_actions: [show]
              workflow_name: pull_request
              no_transition_label: No transition for pull request
              no_transition_icon: fa fa-times
              dropdown_transitions_label: Pull request transitions
              dropdown_transitions_icon: fa fa-archive
              transitions_default_icon: fa fa-step-forward
              transitions_icons:
                  start_review: fa fa-search
                  merge: fa fa-check
                  close: fa fa-times
    Yokai\SonataWorkflow\Controller\WorkflowController:
        autowire: true
        tags: ['controller.service_arguments']

sonata_admin:
    extensions:
        admin.extension.pull_request_workflow:
            admins:
                - admin.pull_request
```

What are these options ?

- `render_actions` : Admin action names on which the extension should render its menu (defaults to `[show, edit]`)
- `workflow_name` : The name of the Workflow to handle (defaults to `null`)
- `no_transition_display` : Whether or not to display a button when no transition is enabled (defaults to `false`)
- `no_transition_label` : The button label when no transition is enabled (defaults to `workflow_transitions_empty`)
- `no_transition_icon` : The button icon when no transition is enabled (defaults to `fa fa-code-fork`)
- `dropdown_transitions_label` : The dropdown button label when there is transitions enabled (defaults to `workflow_transitions`)
- `dropdown_transitions_icon` : The dropdown button icon when there is transitions enabled (defaults to `fa fa-code-fork`)
- `transitions_default_icon` : The default transition icon for all transition (defaults to `null` : no icon)
- `transitions_icons` : A hash with transition name as key and icon as value (defaults to `[]`)


Hook into the transition process
--------------------------------

Let say that when you start a review for a pull request, as a user,
you will be asked to enter which users are involved in the review.

To achieve this, you will be asked to fill a dedicated form.

You only need to create a custom controller for your entity admin :

```yaml
# config/packages/sonata_admin.yml
services:
    admin.pull_request:
        class: App\Admin\PullRequestAdmin
        public: true
        arguments: [~, App\Entity\PullRequest, App\Admin\Controller\PullRequestController]
        tags:
            - { name: 'sonata.admin', manager_type: orm, label: PullRequest }
```

```php
<?php
// src/Admin/Controller/PullRequestController.php

namespace App\Admin\Controller;

use App\Entity\PullRequest;
use App\Form\PullRequest\StartReviewType;
use Sonata\AdminBundle\Controller\CRUDController;
use Yokai\SonataWorkflow\Controller\WorkflowControllerTrait;

class PullRequestController extends CRUDController
{
    use WorkflowControllerTrait;

    protected function preApplyTransition($object, $transition)
    {
        switch ($transition) {
            case 'start_review':
                return $this->startReview($object, $transition);
        }

        return null;
    }

    protected function startReview(PullRequest $object, $transition)
    {
        $form = $this->createForm(
            StartReviewType::class,
            [],
            [
                'action' => $this->admin->generateObjectUrl(
                    'workflow_apply_transition',
                    $object,
                    ['transition' => $transition]
                ),
            ]
        );

        $form->handleRequest($this->getRequest());

        if (!$form->isSubmitted() || !$form->isValid()) {
            $formView = $form->createView();

            return $this->renderWithExtraParams('admin/pull-request/start-review.html.twig', [
                'action' => 'edit',
                'form' => $formView,
                'object' => $object,
                'objectId' => $this->admin->getNormalizedIdentifier($object),
            ], null);
        }

        $data = $form->getData();
        // do something with the submitted data before returning null to continue applying transition

        return null;
    }
}
```



MIT License
-----------

License can be found [here](LICENSE).


Authors
-------

The library was originally created by [Yann Eugoné](https://github.com/yann-eugone).
See the list of [contributors](https://github.com/yokai-php/sonata-workflow/contributors).

---

Thank's to [Prestaconcept](https://github.com/prestaconcept) for supporting this library.
