var html = document.querySelector('html');

document.addEventListener("fluentCommunityUtilReady", function () {
    wp.hooks.addAction('better_messages_update_unread', 'bm_fluent_com', function( unread ){
        var unreadCounters = document.querySelectorAll('.bm-unread-badge');

        unreadCounters.forEach(function( counter ){
            counter.innerHTML = unread;

            if( unread > 0 ){
                counter.style.display = '';
            } else {
                counter.style.display = 'none';
            }
        });
    });

    updateDynamicCSS();

    window.FluentCommunityUtil.hooks.addFilter("fluent_com_portal_routes", "fluent_chat_route", function (a) {
        return a.push({
            path: "/messages",
            name: "better_messages",
            component: {
                template: '<div class="fcom_better_messages_wrap" style="padding: 20px;"><div class="bp-messages-wrap-main" style="height: 900px"></div></div>',
                mounted() {
                    updateDynamicCSS();
                    BetterMessages.initialize();
                    BetterMessages.parseHash();

                },
                beforeRouteLeave(e, n) {
                    if( BetterMessages.isInCall()){
                        return false;
                    }

                    var container = document.querySelector('.bp-messages-wrap-main');
                    if( container ){
                        if( container.reactRoot ) container.reactRoot.unmount()
                        container.remove();
                    }

                    BetterMessages.resetMainVisibleThread();
                }
            },
            meta: {active: "better-messages"}
        }), a;
    });

    installMobileMenuButton();
});

function updateDynamicCSS(){
        var body = document.body;

        if( html.classList.contains('dark') ){
            body.classList.add('bm-messages-dark');
            body.classList.remove('bm-messages-light');
        } else {
            body.classList.add('bm-messages-light');
            body.classList.remove('bm-messages-dark');
        }

        var style = document.querySelector('#bm-fcom-footer-height-style');

        if ( ! style ) {
            style = document.createElement('style');
            style.id = 'bm-fcom-footer-height-style';
            document.head.appendChild(style);
        }

        var css = ':root {';

        var windowHeight = window.innerHeight;
        css += `--bm-fcom-window-height: ${windowHeight}px;`;

        var mobileMenu = document.querySelector('.fcom_mobile_menu');
        if( mobileMenu ) {
            var height = mobileMenu.offsetHeight;
            css += `--bm-fcom-footer-height: ${height}px;`;
        }

        var topMenu = document.querySelector('.fcom_top_menu');

        if( topMenu ) {
            var topMenuHeight = topMenu.offsetHeight ;
            css += `--bm-fcom-menu-height: ${topMenuHeight}px;`;
        }

        style.innerHTML = css + '}';
}

function installMobileMenuButton(){
    var mobileMenu = document.querySelector('.fcom_mobile_menu');
    var betterMessagesHeaderButton = document.querySelector('.fcom_better_messages_menu_li');

    if( ! mobileMenu || ! betterMessagesHeaderButton ){
        setTimeout( installMobileMenuButton, 100 );
    } else {
        var mobileMenuItems = mobileMenu.querySelector('.focm_menu_items');

        var bmMobileMenuItemIcon = betterMessagesHeaderButton.querySelector('.el-icon').innerHTML;
        var bmMobileMenuItemHref = betterMessagesHeaderButton.querySelector('a').href;

        var bmMobileMenuItem = document.createElement('div');
        bmMobileMenuItem.innerHTML = '<a href="' + bmMobileMenuItemHref + '" class="focm_menu_item"><i class="el-icon"><span>' + bmMobileMenuItemIcon + '</span></i><span><span class="bm-unread-badge" style="display: none;"></span></span></a>';


        if (mobileMenuItems.children.length >= 1) {
            mobileMenuItems.insertBefore(bmMobileMenuItem, mobileMenuItems.children[1]);
        } else {
            mobileMenuItems.appendChild(bmMobileMenuItem);
        }
    }

}

const config = { attributes: true, attributeFilter: ['class'] };

// Callback function to execute when mutations are observed
const callback = function(mutationsList, observer) {
    for(let mutation of mutationsList) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
            updateDynamicCSS();
        }
    }
};

// Create an observer instance linked to the callback function
const observer = new MutationObserver(callback);

// Start observing the target node for configured mutations
observer.observe(html, config);
