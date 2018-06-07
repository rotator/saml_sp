<?php

/**
 * @file
 * Contains \Drupal\saml_sp_drupal_login\Form\SamlSpDrupalLoginConfig.
 */

namespace Drupal\saml_sp_drupal_login\Form;


use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\user\Entity\User;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;


class SamlSpDrupalLoginConfig extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'saml_sp_drupal_login_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['saml_sp_drupal_login.config'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form = [], FormStateInterface $form_state) {
    $config = $this->config('saml_sp_drupal_login.config');

    $idps = array();
    // List all the IDPs in the system.
    foreach (saml_sp__load_all_idps() as $machine_name => $idp) {
      $idps[$idp->id] = $idp->label;
    }
    $form['config'] = [
      '#tree' => TRUE,
    ];
    $form['config']['idp'] = array(
      '#type' => 'checkboxes',
      '#options' => $idps,
      '#title' => $this->t('IdP'),
      '#description' => $this->t('Choose the IdP to use when authenticating Drupal logins'),
      '#default_value' => $config->get('idp') ?: array(),
      '#options' => $idps ?: array(),
    );
    $site_name = $this->config('system.site')->get('name');

    $form['config']['logout'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Single Log Out'),
      '#description'    => $this->t('When logging out of %site_name also log out of the IdP', array('%site_name' => $site_name)),
      '#default_value'  => $config->get('logout'),
    );

    $form['config']['update_email'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Update Email address'),
      '#description'    => $this->t('If an account can be found on %site_name but the e-mail address differs from the one provided by the IdP update the email on record in Drupal with the new address from the IdP. This will only make a difference is the identifying information from the IdP is not the email address.', array('%site_name' => $site_name)),
      '#default_value'  => $config->get('update_email'),
    );

    $form['config']['update_language'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Update Language'),
      '#description'    => $this->t('If the account language of %site_name differs from that in the IdP response update to the user\'s account to match.', array('%site_name' => $site_name)),
      '#default_value'  => $config->get('update_language'),
    );

    $form['config']['no_account_authenticated_user_role'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Login users without a user account as an authenticated user.'),
      '#description'    => $this->t('If a user is authenticated by the SAML Service Provider but no matching account can be found the user will be logged in as an authenticated user. This will allow users to be authenticated to receive more permissions than an anonymous user but less than a user with any other role.'),
      '#default_value'  => $config->get('no_account_authenticated_user_role', FALSE),
    );

    $uid = $config->get('no_account_authenticated_user_account');
    $users = $uid ? User::loadMultiple(array($uid => $uid)) : array();
    if (isset($users[$uid])) {
      $user = $users[$uid];
    }
    else {
      $user = NULL;
    }
    if (!empty($users)) {
      $default_value = EntityAutocomplete::getEntityLabels($users);
    }
    else {
      $default_value = NULL;
    }

    $form['config']['no_account_authenticated_user_account'] = array(
      '#type'               => 'entity_autocomplete',
      '#title'              => $this->t('Authenticated user account'),
      '#description'        => $this->t('This is the account with only the authenticated user role which a user is logged in as if no matching account exists. As this account will be used for all users make sure that this account has only the "Authenticated User" role.'),
      '#default_value'      => $user,
      '#target_type'        => 'user',
      '#states'             => array(
        'visible'             => array(
          ':input[name="config[no_account_authenticated_user_role]"]' => array('checked' => TRUE),
        ),
      ),
    );

    $form['config']['force_saml_only'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Force SAML Login'),
      '#description'    => $this->t('The User Login form will not be used, when an anonymous user goes to /user they will be automatically redirected to the SAML authentication page.'),
      '#default_value'  => $config->get('force_saml_only'),
    );

/*
    $form['config']['account_request'] = array(
      '#type'           => 'fieldset',
      '#title'          => $this->t('Accounts'),
      '#description'    => $this->t('Allow Users to request account creation.'),
      '#access'         => ($this->config('user.settings')->get('register') == USER_REGISTER_ADMINISTRATORS_ONLY ? 1 : 0),
    );
/**/
    $form['config']['account_request_request_account'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Allow users without an account to request an account?'),
      '#description'    => $this->t('Since you only allow Administrators to create an account: <a href="@account_settings_url" target="_blank">see Account Settings page</a> should the user who authenticates against the SAML IdP be able to request an account from administrators?', array(
        '@account_settings_url' => \Drupal::url('entity.user.admin_form', array(), array('absolute' => TRUE))
      )),
      '#default_value'  => $config->get('account_request_request_account'),
    );

    $site_mail = $this->config('system.site')->get('mail');
    $form['config']['account_request_site_mail'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Send request to site mail account ( @site_mail )', array('@site_mail' => $site_mail)),
      '#default_value'  => $config->get('account_request_site_mail'),
      '#states'         => array(
        'visible'         => array(
          ':input[name="config[account_request_request_account]"]' => array('checked' => TRUE),
        ),
      ),
    );

    $query = db_select('user__roles', 'ur');
    $query->fields('ur', array('entity_id'));
    $query->condition('ur.deleted', 0);

    $result = $query->execute();
    $admin_options = array();
    $admins = User::loadMultiple($result->fetchCol());

    foreach ($admins AS $u) {
      $admin_options[$u->id()] = $u->getDisplayName() . ' - ' . $u->getEmail();
    }
    $uid = $config->get('account_request_site_administrators');
    $form['config']['account_request_site_administrators'] = array(
      '#type'           => 'checkboxes',
      '#title'          => $this->t('Send request to Site Administrators'),
      '#description'    => $this->t('The request email will be sent to these site Administrators'),
      '#options'        => $admin_options,
      '#default_value'  => $config->get('account_request_site_administrators') ?: array(),
      '#states'         => array(
        'visible'         => array(
          ':input[name="config[account_request_request_account]"]' => array('checked' => TRUE),
        ),
      ),
    );
    return parent::buildForm($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('saml_sp_drupal_login.config');
    $values = $form_state->getValues();

    foreach ($values['config'] AS $key => $value) {
      $config->set($key, $value);
    }
    $config->save();
  }
}
