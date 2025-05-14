<?php
use BetterMessages\React\EventLoop\Loop;
use BetterMessages\React\Http\Browser;
use BetterMessages\React\Stream\ThroughStream;

if( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Better_Messages_OpenAI_API' ) ) {
    class Better_Messages_OpenAI_API
    {

        private $api_key;

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_OpenAI_API();
            }

            return $instance;
        }

        public function __construct()
        {
            $this->api_key = Better_Messages()->settings['openAiApiKey'];
        }

        public function get_api_key()
        {
            return $this->api_key;
        }

        public function get_client()
        {
            return new \BetterMessages\GuzzleHttp\Client([
                'base_uri' => 'https://api.openai.com/v1/',
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->get_api_key(),
                    'Content-Type' => 'application/json',
                ]
            ]);
        }

        public function get_api()
        {
            $api_key = $this->get_api_key();
            return BetterMessages_OpenAI::client($api_key);
        }

        public function check_api_key()
        {
            $api = $this->get_api();

            try {
                $api->models()->list();
                delete_option('better_messages_openai_error');
                return true;
            } catch (Exception $e) {
                update_option( 'better_messages_openai_error', $e->getMessage(), false );
                return $e->getMessage();
            }
        }

        public function get_models()
        {
            $api = $this->get_api();

            try{
                $response = $api->models()->list();

                $models = [];

                foreach ($response->data as $result) {
                    $model_id = $result->id;
                    if( str_contains($model_id, 'gpt') && ! str_contains($model_id, '-realtime-') ) {
                        $models[] = $model_id;
                    }
                }

                sort($models);

                return $models;
            } catch (Exception $e) {
                return new WP_Error( 'openai_error', $e->getMessage() );
            }
        }

        function audioProvider( $bot_id, $bot_user, $message ) {
            global $wpdb;

            $bot_settings = Better_Messages()->ai->get_bot_settings( $bot_id );

            $bot_user_id = absint( $bot_user->id ) * -1;

            $ai_response_id = Better_Messages()->functions->get_message_meta( $message->id, 'ai_response_id' );

            if( ! $ai_response_id ){
                return false;
            }

            $ai_message = Better_Messages()->functions->get_message( $ai_response_id );

            if( ! $ai_message ){
                return false;
            }

            $voice = $bot_settings['voice'];

            $messages = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, sender_id, message 
            FROM `" . bm_get_table('messages') . "` 
            WHERE thread_id = %d 
            AND created_at <= %d
            ORDER BY `created_at` ASC", $message->thread_id, $message->created_at ) );

            $request_messages = [];

            if( ! empty( $bot_settings['instruction'] ) ) {
                $request_messages[] = [
                    'role' => 'system',
                    'content' => apply_filters( 'better_messages_open_ai_bot_instruction', $bot_settings['instruction'], $bot_id, $message->sender_id )
                ];
            }

            foreach ( $messages as $_message ){
                $is_error = Better_Messages()->functions->get_message_meta( $_message->id, 'ai_response_error' );
                if( $is_error ) continue;

                $content = [];

                $content[] = [
                    'type' => 'text',
                    'text' => preg_replace('/<!--(.|\s)*?-->/', '', $_message->message)
                ];

                $role = (int) $_message->sender_id === (int) $bot_user_id ? 'assistant' : 'user';

                if( $role === 'assistant' ) {
                    $audio_id = Better_Messages()->functions->get_message_meta( $_message->id, 'openai_audio_id' );
                    $message_content = preg_replace('/<!--(.|\s)*?-->/', '', $_message->message);

                    if( $audio_id ){
                        $audio_expires_at = Better_Messages()->functions->get_message_meta( $_message->id, 'openai_audio_expires_at' );

                        if( ( time() - $audio_expires_at ) <= -60 ){
                            $voice = Better_Messages()->functions->get_message_meta( $_message->id, 'openai_audio_voice' );

                            $request_messages[] = [
                                'role' => $role,
                                'audio' => [ 'id' => $audio_id ]
                            ];
                        } else {
                            $request_messages[] = [
                                'role' => 'system',
                                'content' => apply_filters( 'better_messages_open_ai_bot_instruction', $bot_settings['instruction'], $bot_id, $message->sender_id )
                            ];
                        }
                    } else if( $transcript = Better_Messages()->functions->get_message_meta( $_message->id, 'openai_audio_transcript') ){
                        $content[] = [
                            'type' => 'text',
                            'text' => $transcript
                        ];
                    } else if( ! empty( $message_content ) ){
                        $content[] = [
                            'type' => 'text',
                            'text' => $message_content
                        ];
                    }
                } else {
                    if( $_message->message === '<!-- BPBM-VOICE-MESSAGE -->' && $attachment_id = Better_Messages()->functions->get_message_meta( $_message->id, 'bpbm_voice_messages', true ) ){
                        $file_path = get_attached_file( $attachment_id );

                        $file_content = file_get_contents( $file_path );

                        $base64 = base64_encode( $file_content );

                        $content[] = [
                            'type' => 'input_audio',
                            'input_audio' => [
                                'data'   => $base64,
                                'format' => 'mp3'
                            ]
                        ];

                    } else {
                        $content[] = [
                            'type' => 'text',
                            'text' => preg_replace('/<!--(.|\s)*?-->/', '', $_message->message)
                        ];
                    }
                }

                if( count( $content ) > 0 ) {
                    $request_messages[] = [
                        'role' => $role,
                        'content' => $content,
                    ];
                }

            }

            $params = [
                'model' => $bot_settings['model'],
                'modalities' => ['text', 'audio'],
                'messages' => $request_messages,
                'user' => (string) $message->sender_id
            ];

            $params['audio'] = [
                'format' => 'mp3',
                'voice' => $voice,
            ];

            try {
                $client = $this->get_client();

                $response = $client->post('chat/completions', [
                    'json' => $params
                ]);

                $body = $response->getBody();
                $data = json_decode($body, true);

                if( isset($data['choices']) && is_array($data['choices']) && count($data['choices']) > 0 ) {
                    if( isset( $data['choices'][0]['message']['audio'] ) ) {
                        $audio = $data['choices'][0]['message']['audio'];
                        $id = $audio['id'];
                        $base64 = $audio['data'];
                        $expires_at = $audio['expires_at'];
                        $transcript = $audio['transcript'];

                        $mp3Data = base64_decode($base64);
                        $name = Better_Messages()->functions->random_string(30);
                        $temp_dir = sys_get_temp_dir();
                        $temp_path = trailingslashit($temp_dir) . $name;

                        try {
                            file_put_contents($temp_path, $mp3Data);

                            $file = [
                                'name' => $name . '.mp3',
                                'type' => 'audio/mp3',
                                'tmp_name' => $temp_path,
                                'error' => 0,
                                'size' => filesize($temp_path)
                            ];

                            BP_Better_Messages_Voice_Messages()->save_voice_message_from_file($file, $ai_response_id);

                            Better_Messages()->functions->update_message_meta($ai_response_id, 'openai_audio_id', $id);
                            Better_Messages()->functions->update_message_meta($ai_response_id, 'openai_audio_transcript', $transcript);
                            Better_Messages()->functions->update_message_meta($ai_response_id, 'openai_audio_expires_at', $expires_at);
                            Better_Messages()->functions->update_message_meta($ai_response_id, 'openai_audio_voice', $voice);
                            Better_Messages()->functions->update_message_meta($ai_response_id, 'ai_response_finish', time());
                            Better_Messages()->functions->delete_message_meta($message->id, 'ai_waiting_for_response');
                            Better_Messages()->functions->delete_thread_meta($message->thread_id, 'ai_waiting_for_response');

                            do_action('better_messages_thread_self_update', $message->thread_id, $message->sender_id);
                            do_action('better_messages_thread_updated', $message->thread_id, $message->sender_id);
                        } finally {
                            if (file_exists($temp_path)) {
                                unlink($temp_path);
                            }
                        }
                    } else if ( isset( $data['choices'][0]['message']['content'] ) ) {
                        $content = $data['choices'][0]['message']['content'];

                        $args =  [
                            'sender_id'    => $ai_message->sender_id,
                            'thread_id'    => $message->thread_id,
                            'message_id'   => $ai_response_id,
                            'content'      => htmlentities( $content )
                        ];

                        Better_Messages()->functions->update_message( $args );

                        //Better_Messages()->functions->update_message_meta( $ai_response_id, 'openai_meta', $part[1] );
                        Better_Messages()->functions->update_message_meta( $ai_response_id, 'ai_response_finish', time() );
                        Better_Messages()->functions->delete_message_meta( $message->id, 'ai_waiting_for_response' );
                        Better_Messages()->functions->delete_thread_meta( $message->thread_id, 'ai_waiting_for_response' );
                        do_action( 'better_messages_thread_self_update', $message->thread_id, $message->sender_id );
                        do_action( 'better_messages_thread_updated', $message->thread_id, $message->sender_id );
                    }
                }

            } catch (\BetterMessages\GuzzleHttp\Exception\GuzzleException $e) {
                $error = 'Request failed: ' . $e->getMessage() . ' - Response: ' . $e->getResponse()->getBody()->getContents();

                $args =  [
                    'sender_id'    => $ai_message->sender_id,
                    'thread_id'    => $ai_message->thread_id,
                    'message_id'   => $ai_message->id,
                    'content'      => $error
                ];

                Better_Messages()->functions->update_message( $args );

                Better_Messages()->functions->delete_message_meta( $message->id, 'ai_waiting_for_response' );
                Better_Messages()->functions->delete_thread_meta( $message->thread_id, 'ai_waiting_for_response' );
                do_action( 'better_messages_thread_self_update', $message->thread_id, $message->sender_id );
                do_action( 'better_messages_thread_updated', $message->thread_id, $message->sender_id );
                Better_Messages()->functions->add_message_meta( $ai_response_id, 'ai_response_error', $error );
                Better_Messages()->functions->add_message_meta( $message->id, 'ai_response_error', $error );
            }
        }

        function chatProvider( $bot_id, $bot_user, $message ) {
            global $wpdb;

            $client = $this->get_api();

            $bot_settings = Better_Messages()->ai->get_bot_settings( $bot_id );

            $bot_user_id = absint( $bot_user->id ) * -1;

            $messages = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, sender_id, message 
            FROM `" . bm_get_table('messages') . "` 
            WHERE thread_id = %d 
            AND created_at <= %d
            ORDER BY `created_at` ASC", $message->thread_id, $message->created_at ) );

            $request_messages = [];

            if( ! empty( $bot_settings['instruction'] ) ) {
                $request_messages[] = [
                    'role' => 'system',
                    'content' => apply_filters( 'better_messages_open_ai_bot_instruction', $bot_settings['instruction'], $bot_id, $message->sender_id )
                ];
            }

            foreach ( $messages as $_message ){
                $is_error = Better_Messages()->functions->get_message_meta( $_message->id, 'ai_response_error' );
                if( $is_error ) continue;

                $content = [];

                $content[] = [
                    'type' => 'text',
                    'text' => preg_replace('/<!--(.|\s)*?-->/', '', $_message->message)
                ];

                if( $bot_settings['images'] ) {
                    $attachments = Better_Messages()->functions->get_message_meta($_message->id, 'attachments', true);

                    if (!empty($attachments)) {
                        foreach ($attachments as $attachment) {
                            $url = $attachment;

                            if (defined('BM_DEV')) {
                                $url = 'https://www.wordplus.org/wp-content/uploads/2023/01/preview.jpg';
                            }

                            $content[] = [
                                "type" => "image_url",
                                "image_url" => ["url" => $url]
                            ];
                        }
                    }
                }

                $request_messages[] = [
                    'role' => $message->sender_id === $bot_user_id ? 'assistant' : 'user',
                    'content' => $content,
                ];
            }

            try{
                $params = [
                    'model' => $bot_settings['model'],
                    'messages' => $request_messages,
                    'user' => $message->sender_id
                ];

                $stream = $client->chat()->createStreamed( $params );

                foreach($stream as $response){
                    $text = $response->choices[0]->delta->content;
                    yield $text;
                }

                yield ['meta', json_encode($stream->meta())];
            } catch (Exception $e) {
                yield ['error', $e->getMessage()];
            }
        }

        function process_reply( $bot_id, $message_id )
        {

            if( wp_get_scheduled_event( 'better_messages_ai_bot_ensure_completion', [ $bot_id, $message_id ] ) ){
                wp_clear_scheduled_hook( 'better_messages_ai_bot_ensure_completion', [ $bot_id, $message_id ] );
            }

            $message = Better_Messages()->functions->get_message( $message_id );

            if( ! $message ){
                return;
            }

            if( empty( Better_Messages()->functions->get_message_meta( $message_id, 'ai_waiting_for_response' ) ) ){
                return;
            }

            $recipient_user_id = $message->sender_id;

            $bot_user = Better_Messages()->ai->get_bot_user( $bot_id );

            if( ! $bot_user ){
                return;
            }

            $settings = Better_Messages()->ai->get_bot_settings( $bot_id );

            $ai_user_id = absint( $bot_user->id ) * -1;
            $ai_thread_id = $message->thread_id;

            $ai_message_id = Better_Messages()->functions->get_message_meta( $message_id, 'ai_response_id' );

            if( $ai_message_id ){
                $ai_message = Better_Messages()->functions->get_message( $ai_message_id );
                if( ! $ai_message ){
                    $ai_message_id = false;
                }
            }

            if( ! $ai_message_id ){
                $ai_message_id = Better_Messages()->functions->new_message([
                    'sender_id'    => $ai_user_id,
                    'thread_id'    => $ai_thread_id,
                    'content'      => '...',
                    'count_unread' => false,
                    'return'       => 'message_id',
                    'error_type'   => 'wp_error'
                ]);

                Better_Messages()->functions->add_message_meta( $ai_message_id, 'ai_response_for', $message_id );

                if( ! is_wp_error( $ai_message_id ) ){
                    Better_Messages()->functions->add_message_meta( $ai_message_id, 'ai_response_start', time() );
                    Better_Messages()->functions->add_message_meta( $message_id, 'ai_response_id', $ai_message_id );
                } else {
                    return;
                }
            }

            // If Audio Model Used
            if( str_contains($settings['model'], '-audio-') && class_exists('BP_Better_Messages_Voice_Messages') ){
                $this->audioProvider( $bot_id, $bot_user, $message );
                return;
            }

            $loop = Loop::get();

            $browser = new Browser($loop);

            $dataProvider = $this->chatProvider( $bot_id, $bot_user, $message );

            $stream = new ThroughStream(function ($data) {
                return $data;
            });

            $parts = [];

            $loop->addPeriodicTimer(0.1, function ($timer) use ($loop, $stream, $dataProvider, $message_id, $ai_user_id, $ai_message_id, $ai_thread_id, &$parts, $recipient_user_id) {
                if ( $dataProvider->valid() ) {
                    $part = $dataProvider->current();

                    if( is_array($part) && $part[0] === 'error' ){
                        $stream->write( $part[1] );
                        $stream->end();
                        $loop->cancelTimer($timer);
                        $loop->stop();

                        $args =  [
                            'sender_id'    => $ai_user_id,
                            'thread_id'    => $ai_thread_id,
                            'message_id'   => $ai_message_id,
                            'content'      => $part[1]
                        ];

                        Better_Messages()->functions->update_message( $args );

                        Better_Messages()->functions->delete_message_meta( $message_id, 'ai_waiting_for_response' );
                        Better_Messages()->functions->delete_thread_meta( $ai_thread_id, 'ai_waiting_for_response' );
                        do_action( 'better_messages_thread_self_update', $ai_thread_id, $recipient_user_id );
                        do_action( 'better_messages_thread_updated', $ai_thread_id, $recipient_user_id );

                        Better_Messages()->functions->add_message_meta( $ai_message_id, 'ai_response_error', $part[1] );
                        Better_Messages()->functions->add_message_meta( $message_id, 'ai_response_error', $part[1] );
                        return;
                    }


                    if( is_array($part) && $part[0] === 'meta' ){
                        $stream->end();
                        $loop->cancelTimer($timer);
                        $loop->stop();

                        $args =  [
                            'sender_id'    => $ai_user_id,
                            'thread_id'    => $ai_thread_id,
                            'message_id'   => $ai_message_id,
                            'content'      => htmlentities( implode('', $parts) )
                        ];

                        Better_Messages()->functions->update_message( $args );

                        Better_Messages()->functions->update_message_meta( $ai_message_id, 'openai_meta', $part[1] );
                        Better_Messages()->functions->update_message_meta( $ai_message_id, 'ai_response_finish', time() );
                        Better_Messages()->functions->delete_message_meta( $message_id, 'ai_waiting_for_response' );
                        Better_Messages()->functions->delete_thread_meta( $ai_thread_id, 'ai_waiting_for_response' );
                        do_action( 'better_messages_thread_self_update', $ai_thread_id, $recipient_user_id );
                        do_action( 'better_messages_thread_updated', $ai_thread_id, $recipient_user_id );
                        return;
                    }

                    $parts[] = $part;
                    $stream->write( $part );
                    $dataProvider->next();
                } else {
                    $stream->end();
                    $loop->cancelTimer($timer);
                    $loop->stop();
                }
            });

            if( Better_Messages()->websocket ) {
                $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');
                $bm_endpoint = $socket_server . 'streamMessage';

                $browser->post($bm_endpoint, [
                    'x-site-id' => Better_Messages()->websocket->site_id,
                    'x-secret-key' => sha1(Better_Messages()->websocket->site_id . Better_Messages()->websocket->secret_key),
                    'x-message-id' => $ai_message_id,
                    'x-thread-id' => $ai_thread_id,
                    'x-recipient-user-id' => $recipient_user_id,
                    'x-sender-user-id' => $ai_user_id,
                ], $stream);
            }

            $loop->run();
        }

        public function reply_to_message( WP_REST_Request $request )
        {
            ignore_user_abort(true);
            set_time_limit(60);

            $bot_id     = (int) $request->get_param( 'bot_id' );
            $message_id = (int) $request->get_param( 'message_id' );

            $this->process_reply( $bot_id, $message_id );
        }
    }
}
