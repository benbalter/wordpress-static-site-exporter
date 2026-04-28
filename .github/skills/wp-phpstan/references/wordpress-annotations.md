# WordPress-specific type annotations

These patterns help PHPStan understand WordPress code where runtime behavior and dynamic typing make inference difficult.

## REST API request typing

PHPStan cannot infer valid request parameters from REST API schemas. Provide explicit type hints for request params.

```php
/**
 * Handle REST API request.
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return WP_REST_Response|WP_Error Response object on success, error on failure.
 *
 * @phpstan-param WP_REST_Request<array{
 *     post?: int,
 *     orderby?: string,
 *     meta_key?: string,
 *     per_page?: int,
 *     status?: array<string>
 * }> $request
 */
public function get_items( $request ) {
    $post_id = $request->get_param( 'post' );
    // PHPStan now knows $post_id is int|null.
}
```

For complex schemas, define reusable types.

```php
/**
 * @phpstan-type PostRequestParams array{
 *     title?: string,
 *     content?: string,
 *     status?: 'publish'|'draft'|'private',
 *     meta?: array<string, mixed>
 * }
 *
 * @phpstan-param WP_REST_Request<PostRequestParams> $request
 */
```

## Hook callbacks

```php
/**
 * Handle status transitions.
 *
 * @param string $new_status
 * @param string $old_status
 * @param WP_Post $post
 */
function handle_transition( string $new_status, string $old_status, WP_Post $post ): void {
    // ...
}

add_action( 'transition_post_status', 'handle_transition', 10, 3 );
```

## Database and iterables

```php
/**
 * @return array<WP_Post> WP_Post objects.
 */
function get_custom_posts(): array {
    $posts = get_posts( [ 'post_type' => 'custom_type', 'numberposts' => -1 ] );
    return $posts;
}

/**
 * @return array<object{id: int, name: string}> Database results.
 */
function get_user_data(): array {
    global $wpdb;

    $results = $wpdb->get_results( "SELECT id, name FROM users", OBJECT );
    return $results ?: [];
}
```

## Hooks (`apply_filters()` and `do_action()`)

Docblocks for `apply_filters()` and `do_action()` are validated. The type of the first `@param` is definitive.

If a third party returns the wrong type for a filter, a PHPStan error is expected and does not require defensive code.

```php
/**
 * Allows hooking into formatting of the price.
 *
 * @param string $formatted The formatted price.
 * @param float  $price     The raw price.
 * @param string $locale    Locale to localize pricing display.
 * @param string $currency  Currency symbol.
 */
return apply_filters( 'autoscout_vehicle_price_formatted', $formatted, $price, $locale, $currency );
```

## Action Scheduler argument shapes

```php
/**
 * Process a scheduled email.
 *
 * @param array{user_id: int, email: string, data: array<string, mixed>} $args
 */
function process_scheduled_email( array $args ): void {
    $user_id = $args['user_id'];
    // ...
}

as_schedule_single_action(
    time() + 3600,
    'process_scheduled_email',
    [
        'user_id' => 123,
        'email' => 'user@example.com',
        'data' => [ 'key' => 'value' ],
    ]
);
```
