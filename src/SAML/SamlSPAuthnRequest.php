<?php

namespace Drupal\saml_sp\SAML;

use OneLogin_Saml2_AuthnRequest;
use OneLogin_Saml2_Settings;

class SamlSPAuthnRequest extends OneLogin_Saml2_AuthnRequest {

  /**
   * Constructs the AuthnRequest object.
   *
   * @param OneLogin_Saml2_Settings $settings Settings
   */
  public function __construct(OneLogin_Saml2_Settings $settings) {
    parent::__construct($settings);
    if (\Drupal::config('saml_sp.settings')->get('debug')){
      if (\Drupal::moduleHandler()->moduleExists('devel')) {
        dpm($this, 'samlp:AuthnRequest');
      }
      else {
        drupal_set_message('samlp:AuthnRequest =></br><pre>' . print_r($this, TRUE) . '</pre>');
      }
    }
  }
}
