document.addEventListener('DOMContentLoaded', () => {
    let userAccountMenus = document.querySelectorAll('.menu-item--user-account');

    if( userAccountMenus.length > 0 ){
        let hivePressUnread = 0;

        userAccountMenus.forEach( userAccountMenu => {
            let link = userAccountMenu.querySelector('a.hp-menu__item--user-account');
            let totalCounter = link.querySelector('small');
            if( totalCounter ){
                if ( ! isNaN(totalCounter.innerText) ) {
                    hivePressUnread = parseInt(totalCounter.innerText);
                }
            }

            let messagesUnread = Better_Messages.total_unread;
            let totalUnread = messagesUnread + hivePressUnread;
            updateTotalCounter( totalUnread );

            wp.hooks.addAction('better_messages_update_unread', 'hivepress_counter', function( unread ){
                messagesUnread = unread;
                let totalUnread = messagesUnread + hivePressUnread;
                updateTotalCounter( totalUnread );
            });

            function updateTotalCounter( totalUnread ){
                if( totalUnread > 0 ){
                    if( totalCounter ){
                        totalCounter.innerText = totalUnread;
                    } else {
                        totalCounter = document.createElement('small');
                        totalCounter.innerText = totalUnread;
                        link.appendChild(totalCounter);
                    }
                } else {
                    if( totalCounter ){
                        totalCounter.remove();
                        totalCounter = null;
                    }
                }
            }
        })
    }


    let widgetAccountMenus = document.querySelectorAll('.hp-menu--user-account');

    if( widgetAccountMenus.length > 0 ){
        widgetAccountMenus.forEach( widgetAccountMenu => {
            updateItemCounter( Better_Messages.total_unread );

            wp.hooks.addAction('better_messages_update_unread', 'hivepress_counter', function( unread ){
                updateItemCounter( unread );
            });

            function updateItemCounter(messagesUnread){
                let userAccountMenuItemLink = widgetAccountMenu.querySelector('.hp-menu__item--bm-messages > a');
                let counter = userAccountMenuItemLink.querySelector('small');
                if( messagesUnread > 0 ){
                    if( counter ){
                        counter.innerText = messagesUnread;
                    } else {
                        counter = document.createElement('small');
                        counter.innerText = messagesUnread;
                        userAccountMenuItemLink.appendChild(counter);
                    }
                } else {
                    if( counter ){
                        counter.remove();
                    }
                }
            }
        });
    }
});


