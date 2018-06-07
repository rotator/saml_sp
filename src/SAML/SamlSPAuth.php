<?php

namespace Drupal\saml_sp\SAML;

use OneLogin_Saml2_Auth;
use OneLogin_Saml2_Settings;
use Drupal\Core\Url;
use Drupal\Core\Link;
use XMLSecurityKey;

class SamlSPAuth extends OneLogin_Saml2_Auth {
  public $auth_callback;
  private $__settings;

  /**
   * @inherit
   *
   * Since $this->_settings is private we need to capture the settings in our
   * own variable
   */
  public function __construct($oldSettings = null) {
    $this->__settings = new OneLogin_Saml2_Settings($oldSettings);
    parent::__construct($oldSettings);
  }

  /**
   * Set the auth callback for after the response is returned
   */
  public function setAuthCallback($callback) {
    $this->auth_callback = $callback;
  }

  /**
   * Initiates the SSO process.
   *
   * @param string $returnTo   The target URL the user should be returned to after login.
   * @param array  $parameters Extra parameters to be added to the GET
   */
   public function login($returnTo = NULL, $parameters = array(), $forceAuthn = FALSE, $isPassive = FALSE, $stay=FALSE, $setNameIdPolicy = TRUE) {
    assert('is_array($parameters)');

    $authnRequest = new SamlSPAuthnRequest($this->getSettings(), $forceAuthn, $isPassive, $setNameIdPolicy);
    $this->_lastRequestID = $authnRequest->getId();

    $samlRequest = $authnRequest->getRequest();
    $parameters['SAMLRequest'] = $samlRequest;
    if (!empty($returnTo)) {
      $parameters['RelayState'] = $returnTo;
    } else {
      $parameters['RelayState'] = OneLogin_Saml2_Utils::getSelfRoutedURLNoQuery();
    }
    $security = $this->getSettings()->getSecurityData();
    if (isset($security['authnRequestsSigned']) && $security['authnRequestsSigned']) {
      $signature = $this->buildRequestSignature($samlRequest, $parameters['RelayState'], $security['signatureAlgorithm']);
      $parameters['SigAlg'] = $security['signatureAlgorithm'];
      $parameters['Signature'] = $signature;
    }
    // get this necessary information for this IdP
    $idp = (object) $this->getSettings()->getIdPData();
    $all_idps = saml_sp__load_all_idps();
    foreach ($all_idps AS $this_idp) {
      if ($this_idp->entity_id == $idp->entityId) {
        $idp->id = $this_idp->id;
      }
    }
    // record the outbound Id of the request
    $id = $authnRequest->getId();
    saml_sp__track_request($id, $idp, $this->auth_callback);
    if (\Drupal::config('saml_sp.settings')->get('debug')) {
      if (\Drupal::moduleHandler()->moduleExists('devel')) {
        dpm($samlRequest, '$samlRequest');
        dpm($parameters, '$parameters');
      } else {
        $decoded_request = base64_decode($samlRequest);
        $inflate = $this->__settings->shouldCompressRequests();

        if ($inflate) {
          $request = gzinflate($decoded_request);
        }
        drupal_set_message(t('Request =><br/> <pre>@request</pre>', ['@request' => $request]));
        drupal_set_message(t('Parameters =><br/> <pre>@parameters</pre>', ['@parameters' => print_r($parameters,TRUE)]));
      }
      $url = Url::fromUri($this->getSSOurl(), array('query' => $parameters));
      return [
        'message' => [
          '#markup' => t('This is a debug page, you can proceed by clicking the following link (this might not work, because "/" chars are encoded differently when the link is made by Drupal as opposed to redirected, as it is when debugging is turned off).') . ' ',
        ],
        'link' =>  Link::fromTextAndUrl(t('test link'), $url)->toRenderable(),
      ];
    }
    $this->redirectTo($this->getSSOurl(), $parameters);
  }

  public function buildRequestSignature($samlRequest, $relayState, $signAlgorithm = XMLSecurityKey::RSA_SHA1) {
    if (\Drupal::config('saml_sp.settings')->get('debug') && \Drupal::moduleHandler()->moduleExists('devel')) {
      dpm('$this->getSettings()->getSecurityData()');
      dpm($this->getSettings()->getSecurityData());
    }
    return parent::buildRequestSignature($samlRequest, $relayState, $signAlgorithm);
  }
}
