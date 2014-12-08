<?php

class DatawrapperPlugin_SigninTwitter extends DatawrapperPlugin {

    public function init() {
        /*$user = UserQuery::create()->findOneById(23);
        DatawrapperSession::login($user);*/

        // register plugin controller under /gallery/
        DatawrapperHooks::register(DatawrapperHooks::GET_PLUGIN_CONTROLLER, array($this, 'process'));

        DatawrapperHooks::register(DatawrapperHooks::ALTERNATIVE_SIGNIN, function() {
            return array(
                'icon' => 'fa fa-twitter',
                'label' => 'Twitter',
                'url' => '/signin/twitter'
            );
        });

        $this->checkLogin();
    }

    public function checkConfig() {
        $config = $this->getConfig();
        return !empty($config['consumer_key']) && !empty($config['consumer_secret']);
    }

    /*
     * check if a user has been signed in properly
     */
    private function checkLogin() {
        if (!$this->checkConfig()) return;
        $config = $this->getConfig();

        if (isset($_SESSION['signin/twitter/status']) && $_SESSION['signin/twitter/status']=='verified') {

            $screenname = $_SESSION['signin/twitter/request_vars']['screen_name'];
            $twitterid = $_SESSION['signin/twitter/request_vars']['user_id'];
            $oauth_token = $_SESSION['signin/twitter/request_vars']['oauth_token'];
            $oauth_token_secret = $_SESSION['signin/twitter/request_vars']['oauth_token_secret'];

            $connection = new TwitterOAuth($config['consumer_key'], $config['consumer_secret'], $oauth_token, $oauth_token_secret);

            // check if we already have this Twitter user in our database
            $user = UserQuery::create()->findOneByOAuthSignIn('twitter::' . $twitterid);
            if (!$user) {
                // if not we create a new one
                $user = new User();
                $user->setCreatedAt(time());
                $user->setOAuthSignIn('twitter::' . $twitterid);
                // we use the email field to store the twitterid
                $user->setEmail('');
                $user->setRole(UserPeer::ROLE_EDITOR); // activate user rigth away
                $user->setName($screenname);
                $user->setSmProfile('https://twitter.com/'.$screenname);
                $user->save();
            }
            DatawrapperSession::login($user, true, true);
        }
    }

    public function process($app) {
        $plugin = $this;

        $app->get('/signin/twitter', function () use ($app, $plugin) {

            $req = $app->request();

            $config = $plugin->getConfig();
            // check if config is complete
            if (!$plugin->checkConfig()) {
                print "Twitter Sign-In is not configured properly!";
                return;
            }

            if (!isset($_SESSION['signin/twitter/token']) || $req->params('oauth_token') != null && $_SESSION['signin/twitter/token'] !== $req->params('oauth_token')) {
                // if token is old, distroy any session and redirect user to index.php
                session_destroy();

                // sign-in user!
                $app->redirect('/');

            } elseif ($req->params('oauth_token') != null && $_SESSION['signin/twitter/token'] === $req->params('oauth_token')) {

                // everything looks good, request access token
                // successful response returns oauth_token, oauth_token_secret, user_id, and screen_name
                $connection = new TwitterOAuth(
                    $config['consumer_key'],
                    $config['consumer_secret'],
                    $_SESSION['signin/twitter/token'],
                    $_SESSION['signin/twitter/token_secret']
                );
                $access_token = $connection->getAccessToken($req->params('oauth_verifier'));

                if ($connection->http_code == '200') {
                    //redirect user to twitter
                    $_SESSION['signin/twitter/status'] = 'verified';
                    $_SESSION['signin/twitter/request_vars'] = $access_token;

                    // unset no longer needed request tokens
                    unset($_SESSION['signin/twitter/token']);
                    unset($_SESSION['signin/twitter/token_secret']);
                    $app->redirect('/');
                } else {
                    die("error, try again later!");
                }

            } else {

                if ($req->get("denied")) {
                    $app->redirect('/');
                    return;
                }

                // fresh authentication
                $connection = new TwitterOAuth($config['consumer_key'], $config['consumer_secret']);
                $request_token = $connection->getRequestToken('http://' . $GLOBALS['dw_config']['domain'] . '/signin/twitter');

                //received token info from twitter
                $_SESSION['signin/twitter/token'] = $request_token['oauth_token'];
                $_SESSION['signin/twitter/token_secret'] = $request_token['oauth_token_secret'];

                // any value other than 200 is failure, so continue only if http code is 200
                if($connection->http_code == '200') {
                    //redirect user to twitter
                    $twitter_url = $connection->getAuthorizeURL($request_token['oauth_token']);
                    $app->redirect($twitter_url);
                } else {
                    die("error connecting to twitter! try again later!");
                }
            }

        });
    }

    public function getRequiredLibraries() {
        return array(
            'vendor/OAuth.php',
            'vendor/twitteroauth.php'
        );
    }

}