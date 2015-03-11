Event.observe(window, 'load', function() {

    // check if we are dealing with OneStepCheckout
    payment.isOneStepCheckout = $$('.onestepcheckout-place-order');
    payment.formOneStepCheckout = $('onestepcheckout-form');
    payment.holdOneStepCheckout = true;

    $('onestepcheckout-form').submit = $('onestepcheckout-form').submit.wrap(function(originalSubmitMethod) {
        new Ajax.Request(opsValidationUrl, {
            method: 'get',
            onSuccess: function(transport) {
                response = {};
                if (transport && transport.responseText){
                    try{
                        response = eval('(' + transport.responseText + ')');
                    }
                    catch (e) {
                        response = {};
                    }
                }
                if (!response.opsError) {
                    originalSubmitMethod();
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
                    var submitelement = $('onestepcheckout-place-order');
                    if (submitelement.parentNode.lastChild.hasClassName('onestepcheckout-place-order-loading')) {
                        submitelement.parentNode.lastChild.remove();
                    }
                    already_placing_order = false;
                    submitelement.removeClassName('grey').addClassName('orange');
                    submitelement.disabled = false;

                    return;
                }
                originalSubmitMethod();
            }
        });
    });

    if(payment.isOneStepCheckout){

        //set the form element
        payment.form = payment.formOneStepCheckout;

         //bind event handlers to buttons
        payment.isOneStepCheckout.each(function(elem){
            elem.observe('click', function(e){

                Event.stop(e);
                if(!payment.holdOneStepCheckout){
                    return;
                }

                if ('ops_directDebit' == payment.currentMethod && payment.holdOneStepCheckout) {
                    window.already_placing_order = true;
                }

                if ('ops_cc' == payment.currentMethod && payment.holdOneStepCheckout) {
                    window.already_placing_order = true;
                }
                //normally this is not called
                payment.save();
            });
        });


         //add new method to restore the palce order state when failure
        payment.toggleOneStepCheckout =  function(action){
            submitelement = $('onestepcheckout-place-order');
            loaderelement = $$('.onestepcheckout-place-order-loading');

            if(action === 'payment'){

                window.already_placing_order = true;
                /* Disable button to avoid multiple clicks */
                submitelement.removeClassName('orange').addClassName('grey');
                submitelement.disabled = true;
                payment.holdOneStepCheckout = true;
            }

            if(action === 'remove'){

                submitelement.removeClassName('grey').addClassName('orange');
                submitelement.disabled = false;

                if(loaderelement){
                    loaderelement = loaderelement[0];
                    if(loaderelement){
                        loaderelement.remove();
                    }
                }

                window.already_placing_order = false;
                payment.holdOneStepCheckout = false;
            }


            return;
        };

        //wrapp save before ogone
        payment.save = payment.save.wrap(function(originalSaveMethod) {
            $('onestepcheckout-place-order').click();
            return;
        });

        //wrap this to toggle the buttons in OneStepCheckout.
        checkout.setLoadWaiting = checkout.setLoadWaiting.wrap(function(originalSetLoadWaiting, param1){

            if(!param1){
                payment.toggleOneStepCheckout('remove');
            }
            originalSetLoadWaiting(param1);
        });
    }
    // check if we are dealing with OneStepCheckout end

});
