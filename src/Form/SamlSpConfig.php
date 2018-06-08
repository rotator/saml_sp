<?php

/**
 * @file
 * Contains \Drupal\saml_sp\Form\SamlSpConfigSPForm.
 */

namespace Drupal\saml_sp\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\Core\Link;
use XMLSecurityKey;
use OneLogin_Saml2_Utils;

class SamlSpConfig extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'saml_sp_config_sp';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('saml_sp.settings');
    $values = $form_state->getValues();
    $this->configRecurse($config, $values['contact'], 'contact');
    $this->configRecurse($config, $values['organization'], 'organization');
    $this->configRecurse($config, $values['security'], 'security');
    $config->set('strict', (boolean) $values['strict']);
    $config->set('debug', (boolean) $values['debug']);
    $config->set('key_location', $values['key_location']);
    $config->set('cert_location', $values['cert_location']);
    $config->set('entity_id', $values['entity_id']);

    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // ensure the cert and key files are provided and exist in the system if
    // signed or encryption options require them
    $values = $form_state->getValues();
    if (
      $values['security']['authnRequestsSigned'] ||
      $values['security']['logoutRequestSigned'] ||
      $values['security']['logoutResponseSigned'] ||
      $values['security']['wantNameIdEncrypted'] ||
      $values['security']['signMetaData']
    ) {
      foreach (['key_location', 'cert_location'] AS $key) {
        if (empty($values[$key])) {
          $form_state->setError($form[$key], $this->t('The %field must be provided.', array('%field' => $form[$key]['#title'])));
        }
        else if (!file_exists($values[$key])) {
          $form_state->setError($form[$key], $this->t('The %input file does not exist.', array('%input' => $values[$key])));
        }
      }
    }
  }

  /**
   * recursively go through the set values to set the configuration
   */
  protected function configRecurse($config, $values, $base = '') {
    foreach ($values AS $var => $value) {
      if (!empty($base)) {
        $v = $base . '.' . $var;
      }
      else {
        $v = $var;
      }
      if (!is_array($value)) {
        $config->set($v, $value);
      }
      else {
        $this->configRecurse($config, $value, $v);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['saml_sp.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form = [], FormStateInterface $form_state) {
    $config = $this->config('saml_sp.settings');

    $form['entity_id'] = array(
      '#type'           => 'textfield',
      '#title'          => $this->t('Entity ID'),
      '#description'    => $this->t('This is the unique name that the Identity Providers will know your site as. Defaults to the login page %login_url', array('%login_url' => \Drupal::url('user.page', array(), array('absolute' => TRUE)))),
      '#default_value'  => $config->get('entity_id'),
    );

    $form['contact'] = array(
      '#type'         => 'fieldset',
      '#title'        => $this->t('Contact Information'),
      '#description'  => $this->t('Information to be included in the federation metadata.'),
      '#tree'         => TRUE,
    );
    $form['contact']['technical'] = array(
      '#type'         => 'fieldset',
      '#title'        => $this->t('Technical'),
    );
    $form['contact']['technical']['name'] = array(
      '#type'           => 'textfield',
      '#title'          => $this->t('Name'),
      '#default_value'  => $config->get('contact.technical.name'),
    );
    $form['contact']['technical']['email'] = array(
      '#type'           => 'textfield',
      '#title'          => $this->t('Email'),
      '#default_value'  => $config->get('contact.technical.email'),
    );
    $form['contact']['support'] = array(
      '#type'         => 'fieldset',
      '#title'        => $this->t('Support'),
    );
    $form['contact']['support']['name'] = array(
      '#type'           => 'textfield',
      '#title'          => $this->t('Name'),
      '#default_value'  => $config->get('contact.support.name'),
    );
    $form['contact']['support']['email'] = array(
      '#type'           => 'textfield',
      '#title'          => $this->t('Email'),
      '#default_value'  => $config->get('contact.support.email'),
    );

    $form['organization'] = array(
      '#type'           => 'fieldset',
      '#title'          => $this->t('Organization'),
      '#description'    => $this->t('Organization information for the federation metadata'),
      '#tree'           => TRUE,
    );
    $form['organization']['name'] = array(
      '#type'           => 'textfield',
      '#title'          => $this->t('Name'),
      '#description'    => $this->t('This is a short name for the organization'),
      '#default_value'  => $config->get('organization.name'),
    );
    $form['organization']['display_name'] = array(
      '#type'           => 'textfield',
      '#title'          => $this->t('Display Name'),
      '#description'    => $this->t('This is a long name for the organization'),
      '#default_value'  => $config->get('organization.display_name'),
    );
    $form['organization']['url'] = array(
      '#type'           => 'textfield',
      '#title'          => $this->t('URL'),
      '#description'    => $this->t('This is a URL for the organization'),
      '#default_value'  => $config->get('organization.url'),
    );

    $form['strict'] = array(
      '#type'           => 'checkbox',
      '#title'          => t('Strict Protocol'),
      '#description'    => t('SAML 2 Strict protocol will be used.'),
      '#default_value'  => $config->get('strict'),
    );

    $form['security'] = array(
      '#type'           => 'fieldset',
      '#title'          => $this->t('Security'),
      '#tree'           => TRUE,
    );
    $form['security']['offered'] = array(
      '#markup'          => t('Signatures and Encryptions Offered:'),
    );
    $form['security']['nameIdEncrypted'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('NameID Encrypted'),
      '#default_value'  =>  $config->get('security.nameIdEncrypted'),
    );
    $form['security']['authnRequestsSigned'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Authn Requests Signed'),
      '#default_value'  => $config->get('security.authnRequestsSigned'),
    );
    $form['security']['logoutRequestSigned'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Logout Requests Signed'),
      '#default_value'  => $config->get('security.logoutRequestSigned'),
    );
    $form['security']['logoutResponseSigned'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Logout Response Signed'),
      '#default_value'  => $config->get('security.logoutResponseSigned'),
    );

    $form['security']['required'] = array(
      '#markup'          => $this->t('Signatures and Encryptions Required:'),
    );
    $form['security']['wantMessagesSigned'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Want Messages Signed'),
      '#default_value'  => $config->get('security.wantMessagesSigned'),
    );
    $form['security']['wantAssertionsSigned'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Want Assertions Signed'),
      '#default_value'  => $config->get('security.wantAssertionsSigned'),
    );
    $form['security']['wantNameIdEncrypted'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Want NameID Encrypted'),
      '#default_value'  => $config->get('security.wantNameIdEncrypted'),
    );
    $form['security']['metadata'] = array(
      '#markup'          => $this->t('Metadata:'),
    );


    $form['security']['signMetaData'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Sign Meta Data'),
      '#default_value'  => $config->get('security.signMetaData'),
    );
    $form['security']['signatureAlgorithm'] = [
      '#type'           => 'select',
      '#title'          => $this->t('Signature Algorithm'),
      '#description'    => $this->t('What algorithm do you want used for messages signatures?'),
      '#options'        => [
        //XMLSecurityKey::DSA_SHA1 => 'DSA SHA-1',
        XMLSecurityKey::RSA_SHA1 => 'SHA-1',
        XMLSecurityKey::RSA_SHA256 => 'SHA-256',
        XMLSecurityKey::RSA_SHA384 => 'SHA-384',
        XMLSecurityKey::RSA_SHA512 => 'SHA-512',
        //XMLSecurityKey::HMAC_SHA1 => 'HMAC SHA-1',
      ],
      '#default_value'   => $config->get('security.signatureAlgorithm'),
    ];
    $form['security']['lowercaseUrlencoding'] = [
      '#type'           => 'checkbox',
      '#title'          => $this->t('Lowercase Url Encoding'),
      //'#description'    => $this->t(""),
      '#default_value'  => $config->get('security.lowercaseUrlencoding'),
    ];
    $suffix ='';
    if (!empty($config->get('cert_location')) &&
        file_exists($config->get('cert_location')) &&
        function_exists('openssl_x509_parse'))
    {
      $encoded_cert = trim(file_get_contents($config->get('cert_location')));
      $cert = openssl_x509_parse(OneLogin_Saml2_Utils::formatCert($encoded_cert));
      // flatten the issuer array
      if (!empty($cert['issuer'])) {
        foreach ($cert['issuer'] AS $key => &$value) {
          if (is_array($value)) {
            $value = implode("/", $value);
          }
        }
      }

      if ($cert) {
        $suffix = t('Name: %cert-name<br/>Issued by: %issuer<br/>Valid: %valid-from - %valid-to', array(
          '%cert-name' => isset($cert['name']) ? $cert['name'] : '',
          '%issuer' => isset($cert['issuer']) && is_array($cert['issuer']) ? implode('/', $cert['issuer']) : '',
          '%valid-from' => isset($cert['validFrom_time_t']) ? date('c', $cert['validFrom_time_t']) : '',
          '%valid-to' => isset($cert['validTo_time_t']) ? date('c', $cert['validTo_time_t']) : '',
        ));
      }
    }
    $form['cert_location'] = array(
      '#type'   => 'textfield',
      '#title'  => $this->t('Certificate Location'),
      '#description'  => $this->t('The location of the x.509 certificate file on the server. This must be a location that PHP can read.'),
      '#default_value' => $config->get('cert_location'),
      '#states' => array(
        'required' => array(
          ['input[name="security[authnRequestsSigned]"' => ['checked' => TRUE],],
          ['input[name="security[logoutRequestSigned]"' => ['checked' => TRUE],],
          ['input[name="security[logoutResponseSigned]"' => ['checked' => TRUE],],
          ['input[name="security[wantNameIdEncrypted]"' => ['checked' => TRUE],],
          ['input[name="security[signMetaData]"' => ['checked' => TRUE],],
        ),
      ),
      '#suffix' => $suffix,
    );

    $form['key_location'] = array(
      '#type'   => 'textfield',
      '#title'  => $this->t('Key Location'),
      '#description'  => $this->t('The location of the x.509 key file on the server. This must be a location that PHP can read.'),
      '#default_value' => $config->get('key_location'),
      '#states' => array(
        'required' => array(
          ['input[name="security[authnRequestsSigned]"' => ['checked' => TRUE],],
          ['input[name="security[logoutRequestSigned]"' => ['checked' => TRUE],],
          ['input[name="security[logoutResponseSigned]"' => ['checked' => TRUE],],
          ['input[name="security[wantNameIdEncrypted]"' => ['checked' => TRUE],],
          ['input[name="security[signMetaData]"' => ['checked' => TRUE],],
        ),
      ),
    );

    $error = FALSE;
    try {
      $metadata = saml_sp__get_metadata(FALSE);
      if (is_array($metadata)) {
        if (isset($metadata[1])) {
          $errors = $metadata[1];
        }
        $metadata = $metadata[0];
      }
    }
    catch (Exception $e) {
      drupal_set_message($this->t('Attempt to create metadata failed: %message.', array('%message' => $e->getMessage())), 'error');
      $metadata = '';
      $error = $e;
    }
    if (empty($metadata) && $error) {
      $no_metadata = $this->t('There is currently no metadata because of the following error: %error. Please resolve the error and return here for your metadata.', array('%error' => $error->getMessage()));
    }
    $form['metadata'] = array(
      '#type'         => 'fieldset',
      '#collapsed'    => TRUE,
      '#collapsible'  => TRUE,
      '#title'        => $this->t('Metadata'),
      '#description'  => $this->t('This is the Federation Metadata for this SP, please provide this to the IdP to create a Relying Party Trust (RTP)'),
    );

    if ($metadata) {
      $form['metadata']['data'] = array(
        '#type'           => 'textarea',
        '#title'          => $this->t('XML Metadata'),
        '#description'    => $this->t(
          'This metadata can also be accessed <a href="@url" target="_blank">here</a>',
          array(
            '@url' => Url::fromRoute('saml_sp.metadata')->toString(),
          )),
        '#disabled'       => TRUE,
        '#rows'           => 20,
        '#default_value'  => trim($metadata),
      );
    }
    else {
      $form['metadata']['none'] = array(
        '#markup'         => $no_metadata,
      );
    }
    $form['debug'] = array(
      '#type'           => 'checkbox',
      '#title'          => $this->t('Turn on debugging'),
      '#description'    => $this->t('Some debugging messages will be shown.'),
      '#default_value'  => $config->get('debug'),
    );
    

    return parent::buildForm($form, $form_state);
  }
}
