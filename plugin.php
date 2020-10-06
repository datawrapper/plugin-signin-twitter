<?php

class DatawrapperPlugin_SigninTwitter extends DatawrapperPlugin {

    public function init() {
        DatawrapperHooks::register(DatawrapperHooks::ALTERNATIVE_SIGNIN, function() {
            return array(
                'icon' => 'fa fa-twitter',
                'label' => 'Twitter',
                'url' => '/signin/twitter'
            );
        });
    }

}
