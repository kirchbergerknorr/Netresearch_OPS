responseIframe = null;
Event.observe(window, 'load', function() {
    payment.toggleOpsDirectDebitInputs = function(country) {
        var bankcode = 'ops_directdebit_bankcode';
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
            if ($(id).hasClassName('required-entry')) {
                $(id).removeClassName('required-entry');
            }
        };
        if ('NL' == country) {
            hideInput(bankcode);
            showInput(bic);
            showInput(iban);
        }
        if ('DE' == country || 'AT' == country) {
            showInput(bankcode);
            hideInput(bic);
            if ('AT' == country) {
                hideInput(iban);
            } else {
                showInput(iban);
            }
        }
    };

    payment.switchMethod = payment.switchMethod.wrap(function (originalSwitchMethod, method) {

        var iframe = $('ops_iframe_ops_cc');
        var doc = null;

        if(iframe != null && iframe.contentDocument) {
            doc = iframe.contentDocument;
        } else if(iframe != null && iframe.contentWindow && iframe.contentWindow.document) {
            doc = iframe.contentWindow.document;
        } else if(iframe != null && iframe.document) {
            doc = iframe.document;
        }
        if (method == 'ops_cc' && (iframe == null || doc.body.innerHTML.length == 0)) {
            document.location.reload();
        }

        return originalSwitchMethod(method);
    });

    order.loadArea = order.loadArea.wrap (function (originalLoadArea, area, indicator, params) {
        if (order.paymentMethod && order.paymentMethod.substr(0,3) == 'ops' && $('ops_iframe_ops_cc') && area) {
            var billing_methodIndex = area.indexOf('billing_method');
            if (billing_methodIndex > -1) {
                area.splice(billing_methodIndex, 1);
            }
        }
        if (order.paymentMethod == 'ops_directDebit' && area == 'card_validation') {
            return;
        }


        originalLoadArea(area, indicator, params);
    });

    order.submit = order.submit.wrap(function (originalSubmitMethod) {
        if ('ops_directDebit' == order.paymentMethod) {
            $('ops_directdebit_iban').value = $('ops_directdebit_iban').value.replace(/\s+/g, '');
            $('ops_directdebit_bic').value = $('ops_directdebit_bic').value.replace(/\s+/g, '');
        }

        if (!$('p_method_ops_cc') || $('p_method_ops_cc').checked == false) {
            return originalSubmitMethod();
        }
        order.originalSubmitMethod = originalSubmitMethod;
        var iframe = $('ops_iframe_ops_cc');
        if (iframe == null || "undefined" == typeof(iframe))  {
            return originalSubmitMethod();
        }

        var doc = null;

        if(iframe.contentDocument) {
            doc = iframe.contentDocument;
        } else if(iframe.contentWindow && iframe.contentWindow.document) {
            doc = iframe.contentWindow.document;
        } else if(iframe.document) {
            doc = iframe.document;
        }
        var form = doc.forms[0];

        order.opsCcFormData["CN"]                = form['CN'].value;
        order.opsCcFormData["CARDNO"]            = form['CARDNO'].value;
        order.opsCcFormData["CVC"]               = form['CVC'].value;
        order.opsCcFormData["ED_MONTH_SELECTOR"] = form['ED_MONTH_SELECTOR'].value;
        order.opsCcFormData["ED_YEAR_SELECTOR"]  = form['ED_YEAR_SELECTOR'].value;

        new Ajax.Request(opsHashUrl, {
            method: 'get',
            parameters: {
                orderid:   form["ORDERID"].value,
                paramplus: form["PARAMPLUS"].value,
                alias:     form["ALIAS"].value,
                storeId:   storeId,
                isAdmin:   1
            },
            onSuccess: function(transport) {
                var data = transport.responseText.evalJSON();
                form["SHASIGN"].value = data.hash;
                form["ED"].value = form["ED_MONTH_SELECTOR"].value + form["ED_YEAR_SELECTOR"].value;
                form.removeChild(form["ED_MONTH_SELECTOR"]);
                form.removeChild(form["ED_YEAR_SELECTOR"]);

                iframe.alreadySet = 'true';

                form.submit();
                iframe.style.visibility = "hidden";
                doc.body.innerHTML = '{ "result" : "waiting" }';
                setTimeout("order.processOpsResponse(500)", 500);
            }
        });
    });

    order.opsCcFormData=[];

    order.processOpsResponse = function(timeOffset) {
        try {
            var responseIframe = $('ops_iframe_ops_cc');
            var responseResult;

            /* payment fails after 30s without response */
            var maxOffset = 30000;

            if(responseIframe.contentDocument) {
                responseResult = responseIframe.contentDocument;
            } else if(responseIframe.contentWindow && responseIframe.contentWindow.document) {
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
            setTimeout("order.processOpsResponse(" + (500+timeOffset) + ")", 500);
            return false;
        } else if ('success' == currentStatus.result) {
            /* show form again, just to have it ready if some other validation fails */
            responseIframe.style.visibility = "visible";
            ops_iframe_ops_cc_prepare(order.opsCcFormData);

            /* submit order */
            order.originalSubmitMethod();

            return true;
        } else {
            responseIframe.style.visibility = "visible";
            ops_iframe_ops_cc_prepare(order.opsCcFormData);
        }

        alert(Translator.translate('Payment failed. Please review your input or select another payment method.'));
        return false;
    };

    order.dataLoaded = function() {
        this.dataShow();
        if (responseIframe) {
            responseIframe.style.visibility = "visible";
            setTimeout("ops_iframe_ops_cc_prepare(order.opsCcFormData)", 500);
        }
    }


    payment.setRequiredDirectDebitFields = function(element) {

        country = $('ops_directdebit_country').value;
        accountNo = 'ops_directdebit_account';
        blz = 'ops_directdebit_bankcode';
        iban = 'ops_directdebit_iban';
        bic = 'ops_directdebit_bic';

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
                if ($('advice-required-entry-ops_directdebit_bankcode')) {
                    $('advice-required-entry-ops_directdebit_bankcode').remove();
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
            if ($('advice-required-entry-ops_directdebit_account')) {
                $('advice-required-entry-ops_directdebit_account').remove();
            }
            $(accountNo).removeClassName('validation-failed');

            $(blz).removeClassName('validation-failed');
            blzClasses.each(function(blzClass) {
                if ($(blz).hasClassName(blzClass)) {
                    $(blz).removeClassName(blzClass);
                }
            });
            if ($('advice-required-entry-ops_directdebit_bankcode')) {
                $('advice-required-entry-ops_directdebit_bankcode').remove();
            }


        }
    }

});

