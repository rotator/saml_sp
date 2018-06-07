(function ($) {

Drupal.samlSp = Drupal.samlSp || {};
Drupal.samlSp.machineName = false;
Drupal.samlSp.addCert = false;
Drupal.samlSp.certs = {};

Drupal.samlSp.idpMetadataParse = function() {
  var xml = $.parseXML($('textarea[name="idp_metadata"]').val().trim());
  Drupal.samlSp.idpMetadataXML = $(xml);
  var entityID = Drupal.samlSp.idpMetadataXML.find('EntityDescriptor, md\\:EntityDescriptor').attr('entityID');

  if (typeof entityID == 'string' && entityID !== '') {
    $('input#edit-idp-entity-id').val(entityID.trim());
    var parser = document.createElement('a');
    parser.href = entityID;
    $('input#edit-idp-label').val(Drupal.samlSp.idpMetadataXML.find('OrganizationDisplayName').text() ? $(Drupal.samlSp.idpMetadataXML.find('OrganizationDisplayName')[0]).text() : parser.hostname).change();
    Drupal.samlSp.machineName = true;
  }

  $(Drupal.samlSp.idpMetadataXML.find('SingleSignOnService, md\\:SingleSignOnService')).each(function() {
    if ($(this).attr('Binding') === 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect' ) {
      $('input#edit-idp-login-url').val($(this).attr('Location').trim());
    }
  });

  $(Drupal.samlSp.idpMetadataXML.find('SingleLogoutService, md\\:SingleSignOnService')).each(function() {
    if ($(this).attr('Binding') === 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect' ) {
      $('input#edit-idp-logout-url').val($(this).attr('Location').trim());
    }
  });

  $(Drupal.samlSp.idpMetadataXML.find('X509Certificate, ds\\:X509Certificate')).each(function () {
    // we put the certs in an object to ensure that none are duplicated
    // the certificate for signing needs to have whitespace trimmed and the new lines removed
    Drupal.samlSp.certs[$(this).text()] = $(this).text().trim();
  });
  $(Drupal.samlSp.idpMetadataXML.find('KeyDescriptor')).each(function () {
    // we put the certs in an object to ensure that none are duplicated
    // the certificate for signing needs to have whitespace trimmed and the new lines removed
    Drupal.samlSp.certs[$(this).text()] = $(this).text().trim();
  });
};

/**
 * Add one cert to the form, trigger the "Add one more" action
 */
Drupal.samlSp.AddCert = function() {
  for (var i in Drupal.samlSp.certs) {
    if (Drupal.samlSp.certs[i].hasOwnProperty) {
      $($('textarea[data-drupal-selector*=edit-idp-x509-cert-]')[$('textarea[data-drupal-selector*=edit-idp-x509-cert-]').length -1]).val(i.trim().replace(/ /g, '').replace(/\r/g, '').replace(/\n/g, ''));
      delete Drupal.samlSp.certs[i];
      $('fieldset[data-drupal-selector=edit-idp-x509-cert] input:submit[data-drupal-selector=edit-idp-x509-cert-actions-add-cert]').mousedown();
      return;
    }
  }
}

$( document ).ajaxComplete(function( event, xhr, settings ) {

  // we need to ensure that the ajax submissions have completed before we start
  // a new one 
  if (settings.url.search('machine_name/transliterate') !== -1 ||
    settings.url.search('saml_sp/idp/add') !== -1) {
    Drupal.samlSp.AddCert();
  }
});

Drupal.behaviors.samlSpIdpForm = {
  'attach': function() {
    $('textarea[name="idp_metadata"]:not(.idp-form-processed)')
      .addClass('idp-form-processed')
      .keyup(function() {
        Drupal.samlSp.idpMetadataParse();
      })
      .mouseup(function() {
        Drupal.samlSp.idpMetadataParse();
      });
  },
};

})(jQuery);
