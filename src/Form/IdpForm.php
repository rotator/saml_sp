<?php

/**
 * @file
 * Contains \Drupal\saml_sp\Form\SamlSpIdpAdd.
 */


namespace Drupal\saml_sp\Form;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

class IdpForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $idp = $this->entity;

    $form['idp_metadata'] = array(
      '#type' => 'textarea',
      '#title'  => t('XML Metadata'),
      '#description' => t('Paste in the metadata provided by the Identity Provider here and the form will be automatically filled out, or you can manually enter the information.'),
    );
    $form['#attached']['library'][] = 'saml_sp/idp_form';

    $form['idp'] = array(
      '#type' => 'fieldset',
      '#tree' => TRUE,
    );

    $form['idp']['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#default_value' => $idp->label(),
      '#description' => t('The human-readable name of this IDP. This text will be displayed to administrators who can configure SAML.'),
      '#required' => TRUE,
      '#size' => 30,
      '#maxlength' => 30,
    );

    $form['idp']['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $idp->id(),
      '#maxlength' => 32,
      '#machine_name' => array(
        'exists' => 'saml_sp_idp_load',
        'source' => array('idp', 'label'),
      ),
      '#description' => t('A unique machine-readable name for this IDP. It must only contain lowercase letters, numbers, and underscores.'),
    );

    $form['idp']['entity_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Entity ID'),
      '#description' => t('The entityID identifier which the Identity Provider will use to identiy itself by, this may sometimes be a URL.'),
      '#default_value' => $idp->entity_id(),
      '#maxlength' => 255,
    );

    $form['idp']['app_name'] = array(
      '#type' => 'textfield',
      '#title' => t('App name'),
      '#description' => t('The app name is provided to the Identiy Provider, to identify the origin of the request.'),
      '#default_value' => $idp->app_name(),
      '#maxlength' => 255,
    );

    $fields = array('mail' => t('Email'));
    if (!empty($extra_fields)) {
      foreach ($extra_fields as $value) {
        $fields[$value] = $value;
      }
    }

    $form['idp']['nameid_field'] = array(
      '#type' => 'select',
      '#title' => t('NameID field'),
      '#description' => t('Mail is usually used between IdP and SP, but if you want to let users change the email address in IdP, you need to use a custom field to store the ID.'),
      '#options' => $fields,
      '#default_value' => $idp->nameid_field(),
    );

    // The SAML Login URL and x.509 certificate must match the details provided
    // by the IDP.
    $form['idp']['login_url'] = array(
      '#type' => 'textfield',
      '#title' => t('IDP Login URL'),
      '#description' => t('Login URL of the Identity Provider server.'),
      '#default_value' => $idp->login_url(),
      '#required' => TRUE,
      '#max_length' => 255,
    );

    $form['idp']['logout_url'] = array(
      '#type' => 'textfield',
      '#title' => t('IDP Logout URL'),
      '#description' => t('Logout URL of the Identity Provider server.'),
      '#default_value' => $idp->logout_url(),
      '#required' => TRUE,
      '#max_length' => 255,
    );

    $form['idp']['x509_cert'] = $this->createCertsFieldset($form_state);

    $form_state->setCached(FALSE);

    $refs = saml_sp_authn_context_class_refs();
    $authn_context_class_ref_options = array(
      $refs['urn:oasis:names:tc:SAML:2.0:ac:classes:Password']                   => t('User Name and Password'),
      $refs['urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport'] => t('Password Protected Transport'),
      $refs['urn:oasis:names:tc:SAML:2.0:ac:classes:TLSClient']                  => t('Transport Layer Security (TLS) Client'),
      $refs['urn:oasis:names:tc:SAML:2.0:ac:classes:X509']                       => t('X.509 Certificate'),
      $refs['urn:federation:authentication:windows']                             => t('Integrated Windows Authentication'),
      $refs['urn:oasis:names:tc:SAML:2.0:ac:classes:Kerberos']                   => t('Kerberos'),
    );
    $default_auth = array();
    foreach ($refs AS $key => $value) {
      $default_auth[$value] = $value;
    }

    $form['idp']['authn_context_class_ref'] = array(
      '#type'           => 'checkboxes',
      '#title'          => t('Authentication Methods'),
      '#description'    => t('What authentication methods would you like to use with this IdP? If left empty all methods from the provider will be allowed.'),
      '#default_value'  => $idp->id ? $idp->authn_context_class_ref() : $default_auth,
      '#options'        => $authn_context_class_ref_options,
      '#required' => FALSE,
    );

    return $form;
  }

  public function createCertsFieldset(FormStateInterface $form_state) {
    $idp = $this->entity;
    $certs = $idp->x509_cert();
    if (!is_array($certs)) {
      $certs = array($idp->x509_cert());
    }

    foreach ($certs AS $key => $value) {
      if ((is_string($value) && empty(trim($value))) || $value == 'Array') {
        unset($certs[$key]);
      }
    }
    $values = $form_state->getValues();

    if (!empty($values['idp']['x509_cert'])) {
      $certs = $values['idp']['x509_cert'];
      unset($certs['actions']);
    }
    $form = array(
      '#type' => 'fieldset',
      '#title' => $this->t('x.509 Certificates'),
      '#description' => t('Enter the application certificate(s) provided by the IdP.'),
      '#prefix' => '<div id="certs-fieldset-wrapper">',
      '#suffix' => '</div>',
    );

    // Gather the number of certs in the form already.
    $num_certs = $form_state->get('num_certs');
    // We have to ensure that there is at least one cert field.
    if ($num_certs === NULL) {
      $num_certs = count($certs) ?: 1;
      $cert_field = $form_state->set('num_certs', $num_certs);
    }
    for ($i = 0; $i < $num_certs; $i++) {
      if (isset($certs[$i])) {
        $encoded_cert = trim($certs[$i]);
      }
      else {
        $encoded_cert = '';
      }
      if (empty($encoded_cert)) {
        $form[$i] = array(
          '#type' => 'textarea',
          '#title' => $this->t('New Certificate'),
          '#default_value' => $encoded_cert,
          '#max_length' => 1024,
        );
        continue;
      }
      $title = t('Certificate');
      if (function_exists('openssl_x509_parse')) {
        $cert = openssl_x509_parse(\OneLogin_Saml2_Utils::formatCert($encoded_cert));
        if ($cert) {
          // flatten the issuer array
          foreach ($cert['issuer'] AS $key => &$value) {
            if (is_array($value)) {
              $value = implode("/", $value);
            }
          }
          $title = t('Name: %cert-name<br/>Issued by: %issuer<br/>Valid: %valid-from - %valid-to', array(
            '%cert-name' => $cert['name'],
            '%issuer' => implode('/', $cert['issuer']),
            '%valid-from' => date('c', $cert['validFrom_time_t']),
            '%valid-to' => date('c', $cert['validTo_time_t']),
          ));
        }
      }

      $form[$i] = array(
        '#type' => 'textarea',
        '#title' => $title,
        '#default_value' => $encoded_cert,
        '#max_length' => 1024,
      );
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['add_cert'] = array(
        '#type' => 'submit',
        '#value' => t('Add one more'),
        '#submit' => array('::addCertCallback'),
        '#ajax' => [
          'callback' => '::addMoreCertsCallback',
          'wrapper' => 'certs-fieldset-wrapper',
        ],
      );
     // If there is more than one name, add the remove button.
    if ($num_certs > 1) {
      $form['actions']['remove_cert'] = [
        '#type' => 'submit',
        '#value' => t('Remove one'),
        '#submit' => array('::removeCertCallback'),
        '#ajax' => [
          'callback' => '::addMoreCertsCallback',
          'wrapper' => 'certs-fieldset-wrapper',
        ],
      ];
    }
    return $form;
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the certs in it.
   */
  public function addMoreCertsCallback(array &$form, FormStateInterface $form_state) {
    $cert_field = $form_state->get('num_certs');
    return $form['idp']['x509_cert'];
  }

  /**
   * Submit handler for the "add cert" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addCertCallback(array &$form, FormStateInterface $form_state) {
    $cert_field = $form_state->get('num_certs');
    $add_button = $cert_field + 1;
    $form_state->set('num_certs', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove cert" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCertCallback(array &$form, FormStateInterface $form_state) {
    $cert_field = $form_state->get('num_certs');
    if ($cert_field > 1) {
      $remove_button = $cert_field - 1;
      $form_state->set('num_certs', $remove_button);
    }
    $form_state->setRebuild();
  }


  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $idp = $this->entity;
    $values = $form_state->getValues();

    foreach ($values['idp'] AS $key => $value) {
      $method = 'set' . ucfirst($key);
      $idp->$method($value);
    }

    $status = $idp->save();

    if ($status) {
      drupal_set_message($this->t('Saved the %label Identity Provider.', array(
        '%label' => $idp->label(),
      )));
    }
    else {
      drupal_set_message($this->t('The %label Identity Provider was not saved.', array(
        '%label' => $example->label(),
      )));
    }

    $form_state->setRedirect('entity.idp.collection');
  }

  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('idp')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }
}
