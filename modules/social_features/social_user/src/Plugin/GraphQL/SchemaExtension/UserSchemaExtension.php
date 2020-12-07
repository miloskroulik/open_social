<?php

namespace Drupal\social_user\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase;

/**
 * Adds user data to the Open Social GraphQL API.
 *
 * @SchemaExtension(
 *   id = "social_user_schema_extension",
 *   name = "Open Social - User Schema Extension",
 *   description = "GraphQL schema extension for Open Social user data.",
 *   schema = "open_social"
 * )
 */
class UserSchemaExtension extends SdlSchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry) {
    $builder = new ResolverBuilder();

    // Root Query fields.
    $registry->addFieldResolver('Query', 'currentUser',
      $builder->compose(
        $builder->produce('current_user'),
        $builder->produce('account_id')
          ->map('account', $builder->fromParent()),
        $builder->produce('entity_load')
          ->map('type', $builder->fromValue('user'))
          ->map('id', $builder->fromParent())
      )
    );

    $registry->addFieldResolver('Query', 'users',
      $builder->produce('query_user')
        ->map('after', $builder->fromArgument('after'))
        ->map('before', $builder->fromArgument('before'))
        ->map('first', $builder->fromArgument('first'))
        ->map('last', $builder->fromArgument('last'))
        ->map('reverse', $builder->fromArgument('reverse'))
        ->map('sortKey', $builder->fromArgument('sortKey'))
    );

    $registry->addFieldResolver('Query', 'user',
      $builder->produce('entity_load_by_uuid')
        ->map('type', $builder->fromValue('user'))
        ->map('uuid', $builder->fromArgument('uuid'))
    );

    // User type fields.
    $registry->addFieldResolver('User', 'uuid',
      $builder->produce('entity_uuid')
        ->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver('User', 'display_name',
      $builder->produce('entity_label')
        ->map('entity', $builder->fromParent())
    );

    $registry->addFieldResolver('User', 'mail',
      $builder->fromPath('entity:user', 'mail.value')
    );

    $registry->addFieldResolver('User', 'created',
      $builder->fromPath('entity:user', 'created.value')
    );

    $registry->addFieldResolver('User', 'updated',
      $builder->fromPath('entity:user', 'changed.value')
    );

    $registry->addFieldResolver('User', 'status',
      $builder->produce('user_status')
        ->map('user', $builder->fromParent())
    );

    $registry->addFieldResolver('User', 'roles',
      $builder->produce('user_roles')
        ->map('user', $builder->fromParent())
    );
  }

}
