Event.observe(window, 'load', function() {
    payment.opsNextStep = function(transport)
    {
        if (transport && transport.responseText){
            try{
                response = eval('(' + transport.responseText + ')');
            }
            catch (e) {
                response = {};
            }
        }
        if (!response.opsError) {
            return payment.nextStep(transport);
        }
        if (response.error) {
            opsValidationFields = payment.opsValidationFields.evalJSON(true);
            errorneousFields = response.fields;
            for(key in errorneousFields) {
                if ($(key)) {
                    if (opsValidationFields[key]) {
                        $(key).removeClassName('validation-passed');
                        $(key).addClassName('validate-string-length');
                        $(key).addClassName('maximum-length-' + opsValidationFields[key]);
                    }
                    if(errorneousFields[key]) {
                        Validation.ajaxError($(key), errorneousFields[key]);
                    }

                }
            }
            checkout.gotoSection(response.goto_section, false);
            return;
        }

        return payment.nextStep(transport);
    };

    payment.onSave = payment.opsNextStep.bindAsEventListener(this);

    payment.save = payment.save.wrap(function(originalSaveMethod) {
        payment.originalSaveMethod = originalSaveMethod;
        //this form element is always set in payment object this.form or payment.form no need to bind to specific
        var opsValidator = new Validation(payment.form);
        if (!opsValidator.validate()) {
            return;
        }
        if ('ops_directDebit' == payment.currentMethod) {
            payment.saveOpsDirectDebit();
            return; //return as you have another call chain here
        }
        if ('ops_cc' == payment.currentMethod) {
            payment.saveOpsCcBrand();
            return; //return as you have another call chain here
        }

        originalSaveMethod();
    });



    payment.saveOpsDirectDebit = function() {
        checkout.setLoadWaiting('payment');
        var countryId = $('ops_directdebit_country_id').value;
        var accountNo = $('ops_directdebit_account_no').value;
        var bankCode  = $('ops_directdebit_bank_code').value;
        var CN        = $('ops_directdebit_CN').value;
        var iban      = $('ops_directdebit_iban').value.replace(/\s+/g, '');
        var bic       = $('ops_directdebit_bic').value.replace(/\s+/g, '');
        new Ajax.Request(opsDirectDebitUrl, {
            method: 'post',
            parameters: { country : countryId, account : accountNo, bankcode : bankCode, CN : CN, iban : iban, bic : bic },
            onSuccess: function(transport) {
                checkout.setLoadWaiting(false);
                payment.originalSaveMethod();
            },
            onFailure: function(transport) {
                checkout.setLoadWaiting(false);
                if (transport.responseText && 0 < transport.responseText.length) {
                    message = transport.responseText;
                } else {
                    message = 'Payment failed. Please select another payment method.';
                }
                alert(Translator.translate(message));
                checkout.setLoadWaiting(false);
            }
        });
    };

    payment.saveOpsCcBrand = function() {
        checkout.setLoadWaiting('payment');
        var owner = $('OPS_CC_CN').value;
        new Ajax.Request(opsSaveCcBrandUrl, {
            method: 'post',
            parameters: { brand : $('OPS_CC_BRAND').value, cn: owner },
            onSuccess: function(transport) {
                if (-1 < opsCcBrandsForAliasInterface.indexOf($('OPS_CC_BRAND').value)) {
                    payment.requestOpsCcAlias();
                } else {
                    checkout.setLoadWaiting(false);
                    //moved inside else otherwise called twice if previous condition is true
                    payment.originalSaveMethod();
                }
            },
            onFailure: function(transport) {
                alert(Translator.translate('Payment failed. Please select another payment method.'));
                checkout.setLoadWaiting(false);
            }
        });
    };

    payment.requestOpsCcAlias = function() {
        checkout.setLoadWaiting('payment');
        var iframe = $('ops_iframe_' + payment.currentMethod);
        var doc = null;

        if(iframe.contentDocument) {
            doc = iframe.contentDocument;
        } else if(iframe.contentWindow) {
            doc = iframe.contentWindow.document;
        } else if(iframe.document) {
            doc = iframe.document;
        }

        doc.body.innerHTML="";
        iframe.alreadySet = false;
        if (payment.opsStoredAliasPresent == false) {
            if ('true' != iframe.alreadySet) {
                form = doc.createElement('form');
                form.id = 'ops_request_form';
                form.method = 'post';
                form.action = opsUrl;
                submit = doc.createElement('submit');
                form.appendChild(submit);

                var cardholder = doc.createElement('input');
                cardholder.id = 'CN';
                cardholder.name = 'CN';
                cardholder.value = $('OPS_CC_CN').value;

                var cardnumber = doc.createElement('input');
                cardnumber.id = 'CARDNO';
                cardnumber.name = 'CARDNO';
                cardnumber.value = $('OPS_CC_CARDNO').value;

                var verificationCode = doc.createElement('input');
                verificationCode.id = 'CVC';
                verificationCode.name = 'CVC';
                verificationCode.value = $('OPS_CC_CVC').value;

                var brandElement = doc.createElement('input');
                brandElement.id = 'BRAND';
                brandElement.name = 'BRAND';
                brandElement.value = $('OPS_CC_BRAND').value;

                var edElement = doc.createElement('input');
                edElement.id = 'ED';
                edElement.name = 'ED';
                edElement.value = $('OPS_CC_ECOM_CARDINFO_EXPDATE_MONTH').value + $('OPS_CC_ECOM_CARDINFO_EXPDATE_YEAR').value;

                var pspidElement = doc.createElement('input');
                pspidElement.id = 'PSPID';
                pspidElement.name = 'PSPID';
                pspidElement.value = opsPspid;

                var orderIdElement = doc.createElement('input');
                orderIdElement.name = 'ORDERID';
                orderIdElement.id = 'ORDERID';
                orderIdElement.value = opsOrderId;

                var acceptUrlElement = doc.createElement('input');
                acceptUrlElement.name = 'ACCEPTURL';
                acceptUrlElement.id = 'ACCEPTURL';
                acceptUrlElement.value = opsAcceptUrl;

                var exceptionUrlElement = doc.createElement('input');
                exceptionUrlElement.name = 'EXCEPTIONURL';
                exceptionUrlElement.id = 'EXCEPTIONURL';
                exceptionUrlElement.value = opsExceptionUrl;

                var paramplusElement = doc.createElement('input');
                paramplusElement.name = 'PARAMPLUS';
                paramplusElement.id = 'PARAMPLUS';
                paramplusElement.value = 'RESPONSEFORMAT=JSON';

                var aliasElement = doc.createElement('input');
                aliasElement.name = 'ALIAS';
                aliasElement.id = 'ALIAS';
                aliasElement.value = opsAlias;

                form.appendChild(pspidElement);
                form.appendChild(brandElement);
                form.appendChild(cardholder);
                form.appendChild(cardnumber);
                form.appendChild(verificationCode);
                form.appendChild(edElement);
                form.appendChild(acceptUrlElement);
                form.appendChild(exceptionUrlElement);
                form.appendChild(orderIdElement);
                form.appendChild(paramplusElement);
                form.appendChild(aliasElement);

                var hash = doc.createElement('input');
                hash.id = 'SHASIGN';
                hash.name = 'SHASIGN';
                saveAliasData = 0;
                if ($('ops_alias_save') && $('ops_alias_save').checked) {
                    saveAliasData = 1;
                }
                new Ajax.Request(opsHashUrl, {
                    method: 'get',
                    parameters: {
                        brand: brandElement.value,
                        orderid: opsOrderId,
                        paramplus: paramplusElement.value,
                        alias: aliasElement.value,
                        saveAlias: saveAliasData,
                        storedAlias: payment.opsStoredAlias
                    },
                    onSuccess: function(transport) {
                        var data = transport.responseText.evalJSON();
                        hash.value = data.hash;
                        aliasElement.value = data.alias;
                        form.appendChild(hash);
                        doc.body.appendChild(form);
                        iframe.alreadySet = 'true';

                        form.submit();

                        doc.body.innerHTML = '{ "result" : "waiting" }';
                        setTimeout("payment.processOpsResponse(500)", 500);
                    }
                });
            }
        } else {
            new Ajax.Request(opsAcceptUrl, {
                method: 'get',
                parameters: {
                    Alias: payment.opsStoredAlias,
                    CVC: $('OPS_CC_CVC').value,
                    CN: $('OPS_CC_CN').value
                },
                onSuccess: function(transport) {
                    doc.body.innerHTML = transport.responseText;
                    setTimeout("payment.processOpsResponse(500)", 500);
                }
            });
        }
    };

    payment.processOpsResponse = function(timeOffset) {
        try {
            var responseIframe = $('ops_iframe_' + payment.currentMethod);
            var responseResult;

            /* payment fails after 30s without response */
            var maxOffset = 30000;

            if(responseIframe.contentDocument) {
                responseResult = responseIframe.contentDocument;
            } else if(responseIframe.contentWindow) {
                responseResult = responseIframe.contentWindow.document;
            } else if(responseIframe.document) {
                responseResult = responseIframe.document;
            }

            //Remove links in JSON response
            //can happen f.e. on iPad <a href="tel:0301125679">0301125679</a> if alias is interpreted as a phone number
            var htmlResponse = responseResult.body.innerHTML.replace(/<a\b[^>]*>/i, '');
            htmlResponse = htmlResponse.replace(/<\/a>/i, '');

            if ("undefined" == typeof(responseResult)) {
                currentStatus = '{ "result" : "waiting" }'.evalJSON();
            } else {
                var currentStatus = htmlResponse.evalJSON();
                if ("undefined" == typeof(currentStatus) || "undefined" == typeof(currentStatus.result)) {
                    currentStatus = '{ "result" : "waiting" }'.evalJSON();
                }
            }
        } catch (e) {
            currentStatus = '{ "result" : "waiting" }'.evalJSON();
        }

        if ('waiting' == currentStatus.result && timeOffset <= maxOffset) {
            setTimeout("payment.processOpsResponse(" + (500+timeOffset) + ")", 500);
            return false;
        } else if ('success' == currentStatus.result) {
            new Ajax.Request(opsCcSaveAliasUrl, {
                method: 'post',
                parameters: { alias : currentStatus.alias,
                              CVC : currentStatus.CVC,
                              CN: $('OPS_CC_CN').value
                },
                onSuccess: function(transport) {
                    var data = transport.responseText;
                    checkout.setLoadWaiting(false);
                    $('OPS_CC_CVC').value='';
                    payment.stashCcData();
                    payment.originalSaveMethod();

                },
                onFailure: function(transport) {
                    payment.applyStashedCcData();
                    //reset the buttons on failure
                    checkout.setLoadWaiting(false);
                }
            });

            return true;
        }

        alert(Translator.translate('Payment failed. Please review your input or select another payment method.'));
        checkout.setLoadWaiting(false);
        return false;
    };

    payment.criticalOpsCcData = ['CN', 'CARDNO', 'CVC'];
    payment.stashedOpsCcData = new Array();

    payment.stashCcData = function() {
        payment.criticalOpsCcData.each(function(item) {
            if (!payment.stashedOpsCcData[item] || $('OPS_CC_' + item).value.length) {
                payment.stashedOpsCcData[item] = $('OPS_CC_' + item).value;
                $('OPS_CC_' + item).removeClassName('required-entry');
                $('OPS_CC_' + item).value = '';
                $('OPS_CC_' + item).disable();
            }
        });
    };

    payment.applyStashedCcData = function() {
        payment.criticalOpsCcData.each(function(item) {
            if ($('OPS_CC_' + item)) {
                if (payment.stashedOpsCcData[item] && 0 < payment.stashedOpsCcData[item].length) {
                    $('OPS_CC_' + item).value = payment.stashedOpsCcData[item];
                }
                $('OPS_CC_' + item).addClassName('required-entry');
                $('OPS_CC_' + item).enable();
            }
        });
    };

    payment.toggleOpsDirectDebitInputs = function(country) {
        var bankcode = 'ops_directdebit_bank_code';
        var bic = 'ops_directdebit_bic';
        var iban = 'ops_directdebit_iban';
        var showInput = function(id) {
            $$('#' + id)[0].up().show();
            if (!$(id).hasClassName('required-entry') && id != 'ops_directdebit_bic' && $('ops_directdebit_iban').value == '') {
                $(id).addClassName('required-entry');
            }
        };
        var hideInput = function(id) {
            $$('#' + id)[0].up().hide();
            $(id).removeClassName('required-entry');
        };
        if ('NL' == country) {
            hideInput(bankcode);
            showInput(bic);
            showInput(iban);
        }
        if ('DE' == country || 'AT' == country) {
            showInput(bankcode);
            hideInput(bic);
            showInput(iban);
        }
        if ('AT' == country) {
            hideInput(iban)
        }
    };


    payment.toggleOpsCcInputs = function() {
        if (-1 < opsCcBrandsForAliasInterface.indexOf($('OPS_CC_BRAND').value)) {
            $('ops_cc_data').show();
        } else {
            $('ops_cc_data').hide();
        }
    };

    if(typeof accordion != 'undefined'){
        accordion.openSection = accordion.openSection.wrap(function(originalOpenSectionMethod, section) {

            if (section.id == 'opc-payment' || section == 'opc-payment') {
                payment.applyStashedCcData();
            }
            if ((section.id == 'opc-payment' || section == 'opc-payment') && 'ops_cc' == payment.currentMethod) {
                if ($('OPS_CC_CN') && $('OPS_CC_CN').hasAttribute('disabled')) {
                    $('OPS_CC_CN').removeAttribute('disabled');
                }
                if ($('OPS_CC_CARDNO') && $('OPS_CC_CARDNO').hasAttribute('disabled')) {
                    $('OPS_CC_CARDNO').removeAttribute('disabled');
                }
                if ($('OPS_CC_CVC') && $('OPS_CC_CVC').hasAttribute('disabled')) {
                    $('OPS_CC_CVC').removeAttribute('disabled');
                }
            }
            originalOpenSectionMethod(section);
        });
    }

    payment.clearOpsCcInputs = function() {
        if (payment.opsStoredAliasPresent == true) {
            $('OPS_CC_CN').value = '';
            $('OPS_CC_CN').removeAttribute('readonly');
            $('OPS_CC_CN').removeClassName('readonly');
            $('OPS_CC_CN').readOnly = false;
            $('OPS_CC_CARDNO').value = '';
            $('OPS_CC_CARDNO').removeClassName('readonly');
            $('OPS_CC_CARDNO').addClassName('validate-cc-number');
            $('OPS_CC_CARDNO').addClassName('validate-cc-type');
            $('OPS_CC_CARDNO').removeAttribute('readonly');
            $('OPS_CC_CARDNO').readOnly = false;;
            $('OPS_CC_ECOM_CARDINFO_EXPDATE_MONTH').selectedIndex = 0;
            $('OPS_CC_ECOM_CARDINFO_EXPDATE_MONTH').removeClassName('readonly');
            $('OPS_CC_ECOM_CARDINFO_EXPDATE_MONTH').removeAttribute('readonly');
            $('OPS_CC_ECOM_CARDINFO_EXPDATE_MONTH').readOnly = false;
            $('OPS_CC_ECOM_CARDINFO_EXPDATE_YEAR').selectedIndex = 0;
            $('OPS_CC_ECOM_CARDINFO_EXPDATE_YEAR').removeClassName('readonly');
            $('OPS_CC_ECOM_CARDINFO_EXPDATE_YEAR').removeAttribute('readonly');
            $('OPS_CC_ECOM_CARDINFO_EXPDATE_YEAR').readOnly = false;
            $('ops_save_alias_li').show();
            payment.opsStoredAliasPresent = false;
        }
    };

    payment.jumpToLoginStep = function() {
        if(typeof accordion != 'undefined'){
            accordion.openSection('opc-login');
            $('login:register').checked = true;
        }
    };

    payment.setRequiredDirectDebitFields = function(element) {

        country = $('ops_directdebit_country_id').value;
        accountNo = 'ops_directdebit_account_no';
        blz = 'ops_directdebit_bank_code';
        iban = 'ops_directdebit_iban';
        bic = 'ops_directdebit_bic';

        if ($(iban).value == '' && $(bic).value == '' && $(accountNo).value == '' && $(blz).value == '') {
            $(iban).addClassName('required-entry');
            $(accountNo).addClassName('required-entry');
            $(blz).addClassName('required-entry');
            return;
        }

        if ($(iban).value == '' && $(bic).value == '' && $(accountNo).value == '' && $(blz).value == '') {
            $(iban).addClassName('required-entry');
            $(accountNo).addClassName('required-entry');
            $(blz).addClassName('required-entry');
            return;
        }

        accountNoClasses = new Array('required-entry');
        blzClasses = new Array('required-entry');
        if (country == 'AT' || (element.id == accountNo || element.id == blz)) {

            $(iban).removeClassName('required-entry');
            $(iban).removeClassName('validation-failed');
            if ($('advice-required-entry-ops_directdebit_iban')) {
                $('advice-required-entry-ops_directdebit_iban').remove();
            }
            accountNoClasses.each(function(accountNoClass) {
                if (!$(accountNo).hasClassName(accountNoClass)) {
                    $(accountNo).addClassName(accountNoClass);
                }
            });

            if (country == 'DE' || country == 'AT') {
                blzClasses.each(function(blzClass) {
                    if (!$(blz).hasClassName(blzClass)) {
                        $(blz).addClassName(blzClass);
                    }
                });
            }


            $(accountNo).removeClassName('validation-passed');
            $(blz).removeClassName('validation-passed');

            if (country == 'NL') {
                $(blz).removeClassName('required-entry');
                $(blz).removeClassName('validation-failed');
                if ($('advice-required-entry-ops_directdebit_bank_code')) {
                    $('advice-required-entry-ops_directdebit_bank_code').remove();
                }
            }
        }
        if ((element.id == iban || element.id == bic)) {
            if (!$(iban).hasClassName('required-entry')) {
                $(iban).addClassName('required-entry')
            }
            if ($(iban).hasClassName('validation-passed')) {
                $(iban).removeClassName('validation-passed')
            }

            accountNoClasses.each(function(accountNoClass) {
                if ($(accountNo).hasClassName(accountNoClass)) {
                    $(accountNo).removeClassName(accountNoClass);
                }
            });
            if ($('advice-required-entry-ops_directdebit_account_no')) {
                $('advice-required-entry-ops_directdebit_account_no').remove();
            }
            $(accountNo).removeClassName('validation-failed');

            $(blz).removeClassName('validation-failed');
            blzClasses.each(function(blzClass) {
                if ($(blz).hasClassName(blzClass)) {
                    $(blz).removeClassName(blzClass);
                }
            });
            if ($('advice-required-entry-ops_directdebit_bank_code')) {
                $('advice-required-entry-ops_directdebit_bank_code').remove();
            }

        }
    }
});
