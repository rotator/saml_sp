<?php

/**
 * @file
 * Contains \Drupal\samlsp\Controller\SamlSPDrupalLoginController.
 */

namespace Drupal\saml_sp_drupal_login\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\saml_sp\Entity\Idp;
use Drupal\Core\Render\HtmlResponse;


/**
 * Provides route responses for the SAML SP module
 */
class SamlSPDrupalLoginController extends ControllerBase {

  /**
   * Initiate a SAML login for the given IdP
   */
  public function initiate(Idp $idp) {
    // Start the authentication process; invoke
    // saml_sp_drupal_login__saml_authenticate() when done.
    $return = saml_sp_start($idp, 'saml_sp_drupal_login__saml_authenticate');
    if (!empty($return)) {
      // something was returned, echo it to the screen
      return $return;
    }
  }
}
