var user_id = parseInt(Better_Messages.user_id);
var timeoutId;

var OneSignalUpdate = (function() {

    return function() {
        BetterMessages.getApi().then( function ( api ) {
            BetterMessages.getSetting( 'oneSignal' ).then( function ( savedOneSignal ) {
                let updateNeeded = false;

                OneSignal.getUserId().then( function (subscriptionId) {
                    if( ! subscriptionId ) return;

                    if( ! savedOneSignal ){
                        updateNeeded = true;
                    } else {
                        if( savedOneSignal.user_id != user_id ){
                            updateNeeded = true;
                        }

                        if( savedOneSignal.subscription_id != subscriptionId ){
                            updateNeeded = true;
                        }
                    }

                    if( updateNeeded ){
                        // Clear the previous timeout
                        if (timeoutId) {
                            clearTimeout(timeoutId);
                        }

                        // Set a new timeout
                        timeoutId = setTimeout(function() {
                            api.post( 'oneSignal/updateSubscription', {
                                subscription_id: subscriptionId
                            }).then( function ( response ) {
                                BetterMessages.updateSetting('oneSignal', {
                                    user_id: user_id,
                                    subscription_id: subscriptionId
                                });

                                OneSignal.setExternalUserId(user_id);
                            }).catch( function ( error ) {
                                console.error( error );
                            })
                        }, 3000 )
                    }
                });
            } )
        } )
    }
})();


if( typeof OneSignal !== 'undefined' ) {
    OneSignal.push(function () {
        OneSignal.isPushNotificationsEnabled().then( function ( isSubscribed ) {
            if( isSubscribed ) OneSignalUpdate();
        });

        OneSignal.on('subscriptionChange', function (isSubscribed) {
            if( isSubscribed ) OneSignalUpdate();
        })
    })
} else {
    // New OneSignal SDK
    window.OneSignalDeferred = window.OneSignalDeferred || [];

    function initNewSDK( OneSignal ){
        if( ! OneSignal.initialized ){
            setTimeout( function() { initNewSDK(OneSignal) }, 1000 );
            return;
        }

        updateUser();

        OneSignal.User.addEventListener('change', function (event) {
            updateUser();
        });

        OneSignal.User.PushSubscription.addEventListener("change", function (event) {
            updateUser();
        });
    }

    function updateUser(){
        BetterMessages.getApi().then( function ( api ) {
            BetterMessages.getSetting('oneSignal2025').then(function (savedOneSignal) {
                let updateNeeded = false;

                if (! savedOneSignal ) {
                    updateNeeded = true;
                } else {
                    if ( savedOneSignal.user_id != OneSignal.User.externalId ) {
                        updateNeeded = true;
                    }

                    if ( savedOneSignal.subscription_id != OneSignal.User.PushSubscription.id ) {
                        updateNeeded = true;
                    }
                }

                if( updateNeeded ){
                    // Clear the previous timeout
                    if (timeoutId) {
                        clearTimeout(timeoutId);
                    }

                    // Set a new timeout
                    timeoutId = setTimeout(function() {
                        api.post( 'oneSignal/updateSubscription', {
                            subscription_id: OneSignal.User.PushSubscription.id
                        }).then( function ( response ) {
                            BetterMessages.updateSetting('oneSignal2025', {
                                user_id: user_id,
                                subscription_id: OneSignal.User.PushSubscription.id
                            });

                            OneSignal.login(user_id.toString());
                        }).catch( function ( error ) {
                            console.error( error );
                        })
                    }, 3000 )
                }

            });
        });


    }

    OneSignalDeferred.push(function(OneSignal) {
        initNewSDK(OneSignal)
    });
}
