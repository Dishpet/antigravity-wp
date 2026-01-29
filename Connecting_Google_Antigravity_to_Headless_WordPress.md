# Connecting a Google Antigravity Agent to a Headless WordPress (WooCommerce) -- Setup Guide

## Introduction and Overview

Integrating your Google Antigravity agent with a headless WordPress site
(with WooCommerce) will allow the agent to autonomously manage content,
products, and even theme code. We will leverage WordPress's built-in
REST API and WooCommerce's API for standard operations, and create a
custom plugin to extend the agent's capabilities for maximum control. By
the end of this guide, your agent will be able to create or update
posts, modify WooCommerce products, and even deploy changes to the
WordPress theme code -- all through secure programmatic interfaces.

**Key approach:** Use WordPress REST APIs for content and products, and
implement a custom "Agent Connector" plugin for advanced tasks (like
editing theme files). This ensures the agent can perform Create, Read,
Update, Delete (CRUD) actions on WordPress resources using standard HTTP
methods[\[1\]](https://kinsta.com/blog/wp-rest-api-custom-endpoint/#:~:text=The%20WordPress%20REST%20API%20is,sites%20programmatically%20using%20standard%20HTTP%C2%A0methods),
while also having an extended interface for tasks not covered by core
APIs.

## Prerequisites and Setup Requirements

Before proceeding, make sure you have the following in place:

-   **WordPress Admin Access:** You'll need an administrator account on
    your WordPress installation (hosted on Hostinger in this case) to
    configure settings and install plugins.
-   **WooCommerce Installed:** Ensure WooCommerce is active on the site
    (since product management is desired).
-   **Permalinks Enabled:** Verify that WordPress **Permalink Settings**
    are not set to "Plain". Friendly URL structures (any option other
    than Plain) are required for the REST API endpoints to function
    properly[\[2\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=,other%20than%20Plain%20will%20work).
-   **SSL (HTTPS):** It's highly recommended your WordPress URL is
    served over HTTPS. WooCommerce's API, for example, requires HTTPS
    for authentication by
    default[\[3\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=Administrator%20and%20Shop%20Manager%20roles,to%20establishing%20a%20secure%20connection).
    This will also keep credentials secure in transit.
-   **Google Antigravity Setup:** Have Google Antigravity installed and
    configured on your local machine (with access to Chrome). The agent
    should be allowed to make web requests. You can adjust the agent's
    autonomy settings (execution and review policies) within Antigravity
    -- for instance, you might start in a review-driven mode so the
    agent asks for confirmation on critical
    actions[\[4\]](https://codelabs.developers.google.com/getting-started-google-antigravity#:~:text=,Agent%20always%20asks%20for%20review).
    Granting *"Always Proceed"* autonomy can let the agent act freely
    (maximizing capabilities) but comes with higher security
    exposure[\[5\]](https://codelabs.developers.google.com/getting-started-google-antigravity#:~:text=,highest%20exposure%20to%20security%20exploits),
    so choose settings wisely.

With these prerequisites met, let's proceed to enabling the necessary
APIs and integrations.

## Enabling the WordPress REST API for Posts and Pages

WordPress's REST API is enabled by default and provides endpoints to
manage content types like posts, pages, categories, users, etc. In a
headless setup, you may already be using these for the front-end. We
will now ensure the agent can **write** to these endpoints (not just
read). By using the REST API, the agent can create and modify content
just as if it were an admin user on the site -- for example, a
`POST /wp-json/wp/v2/posts` request creates a new blog post (equivalent
to WordPress's `wp_insert_post()`
function)[\[6\]](https://developer.wordpress.org/rest-api/glossary/#:~:text=%2A%20%60GET%20%2Fwp,wp_delete_post).

**1. Configure Authentication via Application Passwords:** WordPress
5.6+ supports Application Passwords for REST API authentication, which
is perfect for our use-case. This allows a user (admin) to grant API
access without sharing the main login password.

-   Log in to WordPress as an admin, go to **Users → Profile** (or
    **Users → All Users → Your Profile**).
-   Scroll down to the **Application Passwords** section. Provide a name
    like "AntigravityAgent" and click **Add New Application Password**.
    WordPress will generate a 24-character password for
    you[\[7\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=It%E2%80%99s%20really%20just%20a%20way,So%20you%20basically%20copy%20it).
    **Copy this password and store it securely** (you won't see it again
    once you navigate
    away)[\[7\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=It%E2%80%99s%20really%20just%20a%20way,So%20you%20basically%20copy%20it).
-   This application password, combined with your username, will be used
    for Basic Authentication in API calls. (It functions like a special
    password only for API access -- you can revoke it anytime without
    affecting your normal
    login[\[8\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=WordPress%2C%20you%20use%20your%20username,That%E2%80%99s%20generated%20for%20us)[\[9\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=And%20then%20if%20I%20scroll,an%20admin%2C%20so%20that%E2%80%99s%20everything).)

**2. Test the WordPress REST API (Optional but Recommended):** It's a
good idea to verify that you can access and modify content via the API
before automating with the agent.

-   **Fetch Posts:** Try a read request to ensure the API is reachable.
    For example, open a browser or API client to:

```{=html}
<!-- -->
```
-   GET https://your-domain.com/wp-json/wp/v2/posts

    This should return a JSON list of recent posts. (This GET request is
    public by default, so it doesn't require auth. If this fails,
    re-check permalinks and that nothing is blocking `wp-json`.)

```{=html}
<!-- -->
```
-   **Create a Post:** Use a tool like cURL or Postman to send an
    authenticated request:
-   URL: `https://your-domain.com/wp-json/wp/v2/posts`
-   Method: **POST**
-   Headers:
    `Authorization: Basic <base64Encoded(username:application_password)>`
    (In Postman, choose "Basic Auth" and enter username and the 24-char
    application password as the
    password)[\[10\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=2,Hit%20Send).
    Also set `Content-Type: application/json`.
-   Body (JSON example):

```{=html}
<!-- -->
```
-   {
          "title": "Hello from Antigravity",
          "content": "This post was created via the REST API.",
          "status": "publish"
        }

```{=html}
<!-- -->
```
-   Send the request. If authentication is correct, you should receive a
    `201 Created` response with the new post's data. The post will be
    live on WordPress. (If you get `401 Unauthorized`, the credentials
    are likely incorrect; if you get `rest_forbidden`, the application
    password might not have proper capabilities -- ensure you used an
    admin user and the site is HTTPS.)

**3. Note on Permissions:** The above uses the admin user's privileges.
The REST API will respect WordPress capabilities of the user whose
credentials are
used[\[11\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=editor%2C%20it%E2%80%99s%2C%20you%20know%2C%20all,endpoints%20are%20really%20running%20that)[\[12\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=that%20through%20an%20application%20password%2C,can%20this%20current%20user%20do).
In this case, using an admin's application password means the agent can
do **anything** that an admin user can (creating posts, editing,
deleting, etc.). This is desired for full control, but keep the
credentials safe.

At this stage, your WordPress is ready to accept content changes from
external clients like the Antigravity agent. Next, we'll enable product
management through WooCommerce's API.

## Setting Up WooCommerce REST API for Product Management

WooCommerce has its own REST API (built on WordPress's framework) for
managing store data such as products, orders, customers, etc. We will
generate API keys for the agent to manipulate products. While it's
possible to use the WordPress application password for WooCommerce
endpoints as well, the recommended approach is to use WooCommerce's
**Consumer Key/Secret** pair for authentication. These act like
API-specific credentials tied to a WooCommerce user (usually an admin or
shop manager).

**1. Enable WooCommerce API and Permalinks:**\
Ensure WooCommerce is updated (v3.5+ for API v3) and permalinks are not
plain (we addressed permalinks
earlier)[\[2\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=,other%20than%20Plain%20will%20work).
Also, your admin account should have the **Administrator** or **Shop
Manager** role -- only these roles can generate API keys and access all
endpoints[\[13\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=,to%20establishing%20a%20secure%20connection).

**2. Generate WooCommerce API Keys:**\
Follow these steps in your WordPress dashboard:

-   Navigate to **WooCommerce → Settings → Advanced → REST API** tab.
    (In older WooCommerce versions, the REST API may be under the API
    tab directly.)
-   Click **"Add Key"** (or **"Create an API Key"**). You'll be prompted
    to add details:
-   **Description:** e.g., "Antigravity Agent Key".
-   **User:** select your admin user account (or a dedicated API user
    with admin/shop manager rights).
-   **Permissions:** choose **Read/Write** (so the agent can GET and
    PUT/POST).[\[14\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=4,the%20Generate%20API%20Key%20button)
-   Click **Generate API Key**. WooCommerce will now display your
    **Consumer Key** and **Consumer Secret** (a pair of alphanumeric
    strings)[\[15\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=6,you%E2%80%99ll%20only%20see%20them%20once).
    **Copy both** and save them securely (you won't be able to see the
    secret again once you leave the
    page)[\[15\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=6,you%E2%80%99ll%20only%20see%20them%20once).
    These keys are essentially the username/password for WooCommerce's
    API. (If the keys ever get compromised or you want to revoke access,
    you can revoke or regenerate them in this same screen. Also note
    that if the user tied to the keys is deleted, the keys stop
    working[\[15\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=6,you%E2%80%99ll%20only%20see%20them%20once).)

**3. Test WooCommerce API calls:** Just like with WordPress, it's good
to test that the keys work:

-   **List Products:** Use an API client with Basic Auth: username =
    Consumer Key, password = Consumer
    Secret[\[10\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=2,Hit%20Send).
    Make a GET request to:

```{=html}
<!-- -->
```
-   https://your-domain.com/wp-json/wc/v3/products

    If the keys are correct and the user has permission, you should get
    a JSON array of products (and a status 200 OK). If you get
    authentication errors, double-check you used HTTPS and the exact
    key/secret values.

```{=html}
<!-- -->
```
-   **Update a Product:** Pick a product ID (you can see `id` in the
    list from the previous call, or find it in WooCommerce admin's
    product edit URL). To update, send:
-   URL: `https://your-domain.com/wp-json/wc/v3/products/<PRODUCT_ID>`
-   Method: **PUT**
-   Auth: Basic (Key as user, Secret as pass)
-   Headers: `Content-Type: application/json`
-   Body (JSON): for example, to update price and name:

```{=html}
<!-- -->
```
-   { 
          "name": "New Product Name",
          "regular_price": "34.99"
        }

```{=html}
<!-- -->
```
-   Send the request. A successful response (200 OK) will return the
    product's data with the updated
    fields[\[16\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=To%20edit%20existing%20product%20data,WooCommerce%20dashboard%2C%20follow%20this%20step).
    The changes will reflect on your site's product listing.
-   **Create a Product (optional):** Similarly, you can create new
    products with a **POST** to `/wp-json/wc/v3/products` and a JSON
    body. For example:

```{=html}
<!-- -->
```
-   {
          "name": "Sample Product",
          "type": "simple",
          "regular_price": "29.99",
          "description": "This is a sample product.",
          "categories": [ { "id": 17 } ]
        }

    (This would create a Simple product priced at 29.99 in category ID
    17)[\[17\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=).
    WooCommerce will return the new product's details (including its new
    ID) if
    successful[\[18\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=If%20successful%2C%20the%20response%20will,product%20details%20with%20an%20ID).

At this point, the Antigravity agent can be configured to use the
WooCommerce API keys to manipulate products (create new listings, update
prices, stock, etc., and even delete products with DELETE requests). You
have two sets of credentials: the WordPress Application Password (for
core WP endpoints) and the WooCommerce keys (for store endpoints). In
the next section, we will unify and expand these capabilities by
creating a custom plugin, which will let us do even more (like editing
theme code) through one interface.

## Building a Custom "Antigravity Connector" Plugin for Maximum Control

To maximize flexibility, we will create a custom WordPress plugin that
provides dedicated REST API endpoints for the agent. This plugin will
act as a bridge between the agent and WordPress, allowing the agent to
perform complex or sensitive operations not covered by the standard
APIs. By combining everything into one plugin, we can use a single
authentication method (the WordPress app password) to handle posts,
products, and even run code changes.

**Why a custom plugin?** The default REST API cannot, for example, edit
theme files or execute arbitrary code for security reasons. With a
plugin, we can deliberately create secure endpoints to allow such
actions. We can also simplify multiple operations. (For instance, while
the agent *could* individually call the WP API for posts and Woo API for
products, our unified endpoints can internally call both, or perform
batch operations, etc., if needed.) Essentially, the plugin gives the
agent an "assistant" on the WordPress side that speaks its language.

### 1. Plugin Setup and Activation

-   **Create the Plugin File:** On your Hostinger hosting, access the
    file system (via FTP or Hostinger's File Manager). Navigate to
    `wp-content/plugins/`. Create a new folder, e.g.,
    `antigravity-agent-connector`. Inside that folder, create a file
    named `antigravity-agent-connector.php`.

-   **Plugin Header:** Edit the PHP file and add the standard plugin
    header comment at the top, for example:

```{=html}
<!-- -->
```
-   <?php
        /**
         * Plugin Name: Antigravity Agent Connector
         * Description: Provides custom REST API endpoints for Google Antigravity agent to manage WordPress content, WooCommerce products, and theme code.
         * Version: 1.0
         * Author: (Your Name)
         */
        // Exit if accessed directly
        if (!defined('ABSPATH')) exit;

    This defines the plugin's metadata so WordPress recognizes it. The
    safety check `ABSPATH` ensures the file isn't directly accessible.
    Save the file.

```{=html}
<!-- -->
```
-   **Activate the Plugin:** Go to your WordPress admin dashboard,
    **Plugins** section. You should see "Antigravity Agent Connector" in
    the list. Click **Activate**. (If it doesn't appear, re-check that
    the file is in the correct location and has the proper `.php`
    extension. Also ensure it begins with `<?php` and the comment as
    shown.)

At this point the plugin is active (though it doesn't do anything yet).
Now we'll add code to create custom REST API endpoints.

### 2. Registering Custom REST API Endpoints

We'll use the WordPress REST API infrastructure to add new routes under
a custom namespace. In our plugin file, after the header (and the `exit`
check), add the following code structure:

    add_action('rest_api_init', function () {
        // Posts endpoint
        register_rest_route('antigravity/v1', '/update-post/(?P<id>\d+)', array(
            'methods'  => 'POST',
            'callback' => 'ag_update_post',
            'permission_callback' => function () { 
                return current_user_can('edit_posts'); 
            }
        ));

        // Products endpoint
        register_rest_route('antigravity/v1', '/update-product/(?P<id>\d+)', array(
            'methods'  => 'POST',
            'callback' => 'ag_update_product',
            'permission_callback' => function () { 
                return current_user_can('manage_woocommerce'); 
            }
        ));

        // Theme file edit endpoint
        register_rest_route('antigravity/v1', '/edit-theme-file', array(
            'methods'  => 'POST',
            'callback' => 'ag_edit_theme_file',
            'permission_callback' => function () { 
                return current_user_can('edit_theme_options'); 
            }
        ));
    });

Let's break down what this does:

-   We hook into `rest_api_init` (which fires when the REST API is set
    up) to register our routes. This ensures we only add these endpoints
    when
    needed[\[19\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=To%20make%20this%20available%20via,when%20the%20API%20isn%E2%80%99t%20loaded)[\[20\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=,).
-   **Namespace:** We use `'antigravity/v1'` as our custom namespace.
    This means our endpoints will be accessible under
    `wp-json/antigravity/v1/...`. (Using a unique namespace prevents
    collisions and allows versioning in the
    future[\[21\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=Namespacing)[\[22\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=,%5D).)
-   **Endpoint for Posts:** `/update-post/(?P<id>\d+)` -- this will
    match a URL like `.../update-post/123`, where 123 is a post ID. We
    allow only POST method, and specify a callback `ag_update_post` (a
    function we'll write next). The `permission_callback` ensures the
    user making the request has `edit_posts`
    capability[\[23\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=function%20called%20,when%20the%20API%20isn%E2%80%99t%20loaded)[\[24\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=define%20the%20HTTP%20methods%20allowed%2C,whether%20the%20field%20is%20required).
    (Since we'll be using an admin's application password, this will be
    true; but it's a good practice to include.)
-   **Endpoint for Products:** `/update-product/(?P<id>\d+)` -- similar
    structure. We require `manage_woocommerce` capability (admins and
    shop managers have this) for modifying products. This endpoint will
    let us update WooCommerce product data via our own function.
-   **Endpoint for Theme Files:** `/edit-theme-file` -- we don't need an
    ID in the URL here; the file path will be provided in the request
    body. Only POST is allowed. We require the user to have
    `edit_theme_options` (a capability generally tied to admins who can
    edit themes/plugins). This is a security measure because editing
    theme code is very sensitive.

After adding these routes, the next step is to implement the callback
functions: `ag_update_post`, `ag_update_product`, `ag_edit_theme_file`.

### 3. Implementing Callback Functions for Custom Endpoints

Add the following PHP functions to the plugin file (below the code
above):

    function ag_update_post($request) {
        $post_id = $request['id'];
        $params  = $request->get_json_params();  // get JSON payload as array[25][26]

        if (empty($post_id) || empty($params)) {
            return new WP_Error('missing_data', 'Post ID or update data not provided', array('status' => 400));
        }
        // Prepare post data array for update
        $post_data = array('ID' => intval($post_id));
        if (!empty($params['title']))    $post_data['post_title']   = sanitize_text_field($params['title']);
        if (!empty($params['content']))  $post_data['post_content'] = $params['content']; // Assume content is HTML, sanitize as needed.
        if (!empty($params['excerpt']))  $post_data['post_excerpt'] = sanitize_text_field($params['excerpt']);
        if (isset($params['status']))    $post_data['post_status']  = sanitize_text_field($params['status']);

        // Update the post
        $result = wp_update_post($post_data, true);
        if (is_wp_error($result)) {
            // Return error if update failed
            return new WP_Error('update_failed', $result->get_error_message(), array('status' => 500));
        }
        // Optionally, you could fetch and return the updated post object:
        $updated_post = get_post($result);
        return array(
            'post_id' => $result,
            'title'   => $updated_post->post_title,
            'status'  => $updated_post->post_status
        );
    }
    function ag_update_product($request) {
        $product_id = $request['id'];
        $params     = $request->get_json_params();
        if (empty($product_id) || empty($params)) {
            return new WP_Error('missing_data', 'Product ID or update data not provided', array('status' => 400));
        }
        if (!function_exists('wc_get_product')) {
            return new WP_Error('no_woo', 'WooCommerce not active or missing', array('status' => 500));
        }
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('not_found', 'Product not found', array('status' => 404));
        }

        // Update fields if provided
        if (!empty($params['name']))         $product->set_name(sanitize_text_field($params['name']));
        if (isset($params['regular_price'])) $product->set_regular_price($params['regular_price']);
        if (isset($params['sale_price']))    $product->set_sale_price($params['sale_price']);
        if (isset($params['stock']))         $product->set_stock_quantity(intval($params['stock']));
        if (isset($params['stock_status']))  $product->set_stock_status(sanitize_text_field($params['stock_status']));
        if (!empty($params['description']))  $product->set_description($params['description']);

        // You can extend this to handle more fields (categories, images, etc.)

        // Save the product (commit changes)
        $product->save();  // uses WooCommerce CRUD to update database[27]

        // Return a success response (could include some product data)
        return array(
            'product_id' => $product->get_id(),
            'name'       => $product->get_name(),
            'price'      => $product->get_price()
        );
    }
    function ag_edit_theme_file($request) {
        $params = $request->get_json_params();
        if (empty($params['file']) || !isset($params['code'])) {
            return new WP_Error('missing_data', 'File path or code content not provided', array('status' => 400));
        }

        $relative_path = sanitize_text_field($params['file']);
        // Construct full path to the file in the active theme directory
        $theme_path = get_stylesheet_directory();  // path to active theme (child theme if exists)
        $full_path  = $theme_path . '/' . $relative_path;

        // Basic security check: ensure the path is within the theme directory
        if (strpos(realpath($full_path), realpath($theme_path)) !== 0 || !file_exists($full_path)) {
            return new WP_Error('file_error', 'Invalid file path', array('status' => 400));
        }

        // Attempt to write the new code to the file
        if (is_writable($full_path)) {
            $newCode = $params['code'];
            // (Optional: you might backup the original file here)
            $result = file_put_contents($full_path, $newCode);
            if ($result === false) {
                return new WP_Error('write_failed', 'Failed to write to file', array('status' => 500));
            }
        } else {
            return new WP_Error('perm_denied', 'File is not writable', array('status' => 500));
        }

        return array('success' => true, 'file' => $relative_path);
    }

Let's summarize what these do:

-   `ag_update_post`**:** Takes a post ID from the URL and a JSON body
    of fields to update (title, content, etc.). It uses `wp_update_post`
    to apply the changes. If successful, it returns the post ID and some
    basic info. This mirrors what the native API could do (we're
    essentially wrapping a core endpoint), but you could extend this to
    do combined operations (e.g., update meta fields at the same time or
    handle custom post types).
-   `ag_update_product`**:** Uses WooCommerce's internal CRUD interface
    to update a product. We fetch the product object with
    `wc_get_product`. If found, we set various properties if they were
    provided in the JSON (price, stock, name, etc.). We then call
    `$product->save()` to persist
    changes[\[27\]](https://rudrastyh.com/woocommerce/create-product-programmatically.html#:~:text=It%20is%20the%20most%20correct,and%20it%20could%20cause%20bugs).
    This is better than directly manipulating post meta because it
    triggers WooCommerce's internal hooks and ensures data
    consistency[\[27\]](https://rudrastyh.com/woocommerce/create-product-programmatically.html#:~:text=It%20is%20the%20most%20correct,and%20it%20could%20cause%20bugs).
    The function finally returns a few key fields (you can customize
    this or return the whole product data by doing
    `$product->get_data()`).
-   `ag_edit_theme_file`**:** Allows editing of a theme file by
    specifying the filename (relative to the theme directory) and the
    new code content. We restrict the path to the active theme directory
    (`get_stylesheet_directory()` gives the path to the current theme or
    child theme). The code checks that the file exists and is within the
    theme folder (to avoid any directory traversal outside). If the file
    is writable, we use `file_put_contents` to write the provided code.
    The agent can use this to modify, say, `header.php`, `footer.php`,
    `functions.php`, CSS files, etc., on the fly. We return a success
    flag and the file name if all goes well. (In a real scenario, you
    might add more safety like syntax checking or limiting to certain
    file types, but that's up to your trust in the agent.)

**Save** the plugin file after adding these functions. The custom API is
now ready.

### 4. Using the Custom API Endpoints

With the plugin active, your WordPress REST API index (`/wp-json/`)
should now list a namespace `antigravity/v1` indicating our new
routes[\[28\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=,%5D).
The Google Antigravity agent (or any client) can use these endpoints as
follows. We'll use the WordPress Application Password for authentication
(the agent can use the same Basic Auth as it does for default WP
endpoints):

-   **Update a Post via custom endpoint:**

```{=html}
<!-- -->
```
-   POST https://your-domain.com/wp-json/antigravity/v1/update-post/123
        Authorization: Basic <base64(admin_username:app_password)>
        Content-Type: application/json

        {
          "title": "Updated by Antigravity Agent",
          "content": "This post content was updated by the agent.",
          "status": "draft"
        }

    This will call `ag_update_post` on post ID 123. The post's title,
    content, and status will change accordingly (if that ID exists and
    the user is authorized). The JSON response will indicate success or
    error. This single call abstracts what would otherwise be a call to
    the core `/posts/123` endpoint -- we created it mostly as a proof of
    concept. (The agent could use the core endpoint instead, but using
    our endpoint means we could add extra logic if needed.)

```{=html}
<!-- -->
```
-   **Update a WooCommerce Product:**

```{=html}
<!-- -->
```
-   POST https://your-domain.com/wp-json/antigravity/v1/update-product/456
        Authorization: Basic <base64(admin_username:app_password)>
        Content-Type: application/json

        {
          "regular_price": "19.99",
          "sale_price": "17.99",
          "stock": 50,
          "name": "Agent-Tuned Product Name"
        }

    This will update product ID 456's price, sale price, stock quantity,
    and name by calling our `ag_update_product`. Under the hood, it's
    using WooCommerce PHP API to set those values and saving the
    product. The response will confirm the operation (with the updated
    price, name, etc.). This combined approach avoids having to
    separately use WooCommerce consumer keys in the agent, since we're
    authenticating with the WordPress admin's app password. The agent
    thus only needs one auth method to handle both posts and products
    through our plugin's endpoints.

```{=html}
<!-- -->
```
-   **Edit a Theme File:**

```{=html}
<!-- -->
```
-   POST https://your-domain.com/wp-json/antigravity/v1/edit-theme-file
        Authorization: Basic <base64(admin_username:app_password)>
        Content-Type: application/json

        {
          "file": "footer.php",
          "code": "<!-- Updated footer by Antigravity agent on 2026-01-29 -->\n" + 
                  "<?php /* new PHP code or HTML here */ ?>"
        }

    This will replace the contents of the active theme's `footer.php`
    with the provided code. A safer practice before doing this is to
    have the agent fetch the current file (perhaps via an additional
    custom GET endpoint we could create, or via FTP) to modify it
    intelligently, rather than overwrite blindly. Nonetheless, this
    endpoint gives the agent direct write access. The response
    `{ "success": true, "file": "footer.php" }` indicates the file was
    written. If there's an error (e.g., file not found or not writable),
    the response will be an error with a message and HTTP status code.

**Important:** Whenever the agent updates theme code, there is a risk of
introducing syntax errors or bugs that could crash the site (e.g., a PHP
parse error). It's recommended to test such changes on a staging site or
ensure you have FTP access to undo changes if needed. The agent might be
sophisticated enough to detect and fix mistakes, but always have a
recovery plan (for example, keep backups of critical theme files or use
a child theme so you can switch back if something goes wrong).

## Final Tips and Considerations

**Secure Your Endpoints:** Our custom endpoints are protected by
WordPress capabilities and require authentication. As long as you keep
the application password secret, only your agent (or anyone with those
credentials) can hit them. Do not expose this password in any public
frontend code. If you suspect it's compromised, revoke it in WordPress
immediately (Profile \> Application Passwords). For WooCommerce keys,
treat them with equal care -- you can regenerate/revoke as
needed[\[15\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=6,you%E2%80%99ll%20only%20see%20them%20once).

**Testing and Gradual Rollout:** Before unleashing the agent on your
production site, test these API calls individually. Use a tool like
Postman to manually create a post, update a product, and edit a small
theme file (perhaps create a dummy file for testing) to ensure
everything works. This will help catch any issues (like file permission
problems or endpoint errors) early. Once verified, you can configure the
Antigravity agent's prompts or code to use these endpoints. It might be
wise initially to have the agent operate in a mode where it asks for
confirmation before executing changes, given the breadth of its
access[\[4\]](https://codelabs.developers.google.com/getting-started-google-antigravity#:~:text=,Agent%20always%20asks%20for%20review).
As you gain confidence, you could grant it more autonomy (for example,
allowing it to run browser actions or JS without asking every
time)[\[5\]](https://codelabs.developers.google.com/getting-started-google-antigravity#:~:text=,highest%20exposure%20to%20security%20exploits).

**Agent Configuration:** In Google Antigravity, you'll likely use the
**Agent Manager** to set up tasks for the agent. You can prompt the
agent with something like: *"Connect to the WordPress REST API at*
`https://my-site.com` *using these credentials, and do X"*. The
Antigravity agent can make HTTP requests (it has a built-in browser and
can run fetches). Ensure the agent's environment allows cross-domain
requests or use its browser control to call the APIs. If needed, you can
install the Antigravity browser extension which helps the agent interact
with web
pages[\[29\]](https://www.reddit.com/r/vibecoding/comments/1p4in9f/the_unspoken_hero_of_antigravity_browser/#:~:text=The%20unspoken%20hero%20of%20Antigravity%3A,automatic%20screenshots%20as%20reference),
but since we're using JSON APIs, a direct HTTP call is preferable.

**Using GraphQL (Alternative):** If you are already using WPGraphQL for
your headless setup, you could similarly give the agent access via
GraphQL queries and mutations. (For example, WPGraphQL with the
WooCommerce and WPGraphQL extension could allow product and post
mutations.) This would require an auth token (JWT or cookie) and doesn't
natively allow file edits, so our REST plugin method is more
straightforward for the theme editing aspect. It's fine to stick with
REST as outlined above, as it's well-supported and easier to call from
the agent.

**Maintenance:** Keep WordPress, WooCommerce, and your custom plugin
updated. If WordPress introduces changes to the REST API or
authentication in the future, adapt accordingly. For instance, WordPress
might enforce application password usage over plain Basic Auth with user
password (which it already prefers). Our method uses application
passwords and official WooCommerce keys, so it is aligned with best
practices.

**Troubleshooting:** If an API call from the agent isn't working, check
the response codes and messages. Common issues include 401 Unauthorized
(auth incorrect), 403 Forbidden (auth correct but user lacks capability
-- e.g., using a non-admin app password for an admin-only endpoint), or
404 Not Found (endpoint URL incorrect or plugin not active). You can
also check the server's error logs if a PHP error occurs (like in the
theme editing function). Adjust the plugin as needed (for example,
adding more validation or logging).

**Backups and Version Control:** When allowing an agent to modify
content and code, it's prudent to have backups. Use a backup plugin or
Hostinger's backup service to take periodic snapshots. For theme code,
consider version controlling your theme (with Git) so you can track
changes the agent makes and revert if necessary. This also helps you
review the agent's code changes in a human-readable format.

**Maximum Flexibility Achieved:** By combining the default
WordPress/WooCommerce APIs with a custom plugin, we have created a
powerful interface for the Antigravity agent: - Content management is
handled through the WordPress REST API (with the agent acting as an
authorized
user)[\[1\]](https://kinsta.com/blog/wp-rest-api-custom-endpoint/#:~:text=The%20WordPress%20REST%20API%20is,sites%20programmatically%20using%20standard%20HTTP%C2%A0methods). -
Product management is handled through WooCommerce's API (either directly
with keys or indirectly via our plugin). - Code and configuration
changes are possible through our custom endpoints (e.g., editing theme
files), granting the agent capabilities akin to an administrator who can
update theme/plugin code.

With this setup in place, your Google Antigravity agent should be able
to **plan, execute, and verify complex tasks** involving your WordPress
site, truly leveraging the "agentic" development approach. For example,
you could instruct the agent to "update the product prices by 10% and
add a notice in the footer about a sale," and it could use the APIs to
update product data and then edit the theme's footer file -- all
autonomously. Always monitor the agent's changes, especially early on,
to ensure everything runs smoothly and adjust its autonomy settings as
you see fit for
safety[\[30\]](https://codelabs.developers.google.com/getting-started-google-antigravity#:~:text=,Custom%20configuration)[\[31\]](https://codelabs.developers.google.com/getting-started-google-antigravity#:~:text=The%20Review,to%20the%20user%20for%20approval).

By following this guide, you have effectively connected your headless
WordPress (and WooCommerce store) to the Antigravity agent in an
efficient and flexible way. Happy coding with your new AI-enhanced
WordPress setup!

**Sources:**

-   WordPress Developer Handbook -- *REST API Basics and Custom
    Endpoints*[\[6\]](https://developer.wordpress.org/rest-api/glossary/#:~:text=%2A%20%60GET%20%2Fwp,wp_delete_post)[\[19\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=To%20make%20this%20available%20via,when%20the%20API%20isn%E2%80%99t%20loaded)[\[20\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=,)
-   Hostinger Tutorials -- *WooCommerce API usage (Generating Keys,
    Example
    Requests)*[\[14\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=4,the%20Generate%20API%20Key%20button)[\[16\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=To%20edit%20existing%20product%20data,WooCommerce%20dashboard%2C%20follow%20this%20step)
-   Misha Rudrastyh -- *Correct Way to Programmatically Update
    WooCommerce
    Products*[\[27\]](https://rudrastyh.com/woocommerce/create-product-programmatically.html#:~:text=It%20is%20the%20most%20correct,and%20it%20could%20cause%20bugs)
-   Brian Coords -- *Using WordPress Application Passwords for REST
    API*[\[7\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=It%E2%80%99s%20really%20just%20a%20way,So%20you%20basically%20copy%20it)
-   Google Codelabs -- *Getting Started with Google Antigravity (Agent
    Policies)*[\[5\]](https://codelabs.developers.google.com/getting-started-google-antigravity#:~:text=,highest%20exposure%20to%20security%20exploits)

[\[1\]](https://kinsta.com/blog/wp-rest-api-custom-endpoint/#:~:text=The%20WordPress%20REST%20API%20is,sites%20programmatically%20using%20standard%20HTTP%C2%A0methods)
How to create custom WordPress REST API endpoints - Kinsta®

<https://kinsta.com/blog/wp-rest-api-custom-endpoint/>

[\[2\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=,other%20than%20Plain%20will%20work)
[\[3\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=Administrator%20and%20Shop%20Manager%20roles,to%20establishing%20a%20secure%20connection)
[\[10\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=2,Hit%20Send)
[\[13\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=,to%20establishing%20a%20secure%20connection)
[\[14\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=4,the%20Generate%20API%20Key%20button)
[\[15\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=6,you%E2%80%99ll%20only%20see%20them%20once)
[\[16\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=To%20edit%20existing%20product%20data,WooCommerce%20dashboard%2C%20follow%20this%20step)
[\[17\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=)
[\[18\]](https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api#:~:text=If%20successful%2C%20the%20response%20will,product%20details%20with%20an%20ID)
WooCommerce API comprehensive guide

<https://www.hostinger.com/tutorials/how-to-generate-woocommerce-api>

[\[4\]](https://codelabs.developers.google.com/getting-started-google-antigravity#:~:text=,Agent%20always%20asks%20for%20review)
[\[5\]](https://codelabs.developers.google.com/getting-started-google-antigravity#:~:text=,highest%20exposure%20to%20security%20exploits)
[\[30\]](https://codelabs.developers.google.com/getting-started-google-antigravity#:~:text=,Custom%20configuration)
[\[31\]](https://codelabs.developers.google.com/getting-started-google-antigravity#:~:text=The%20Review,to%20the%20user%20for%20approval)
Getting Started with Google Antigravity  \|  Google Codelabs

<https://codelabs.developers.google.com/getting-started-google-antigravity>

[\[6\]](https://developer.wordpress.org/rest-api/glossary/#:~:text=%2A%20%60GET%20%2Fwp,wp_delete_post)
Glossary -- REST API Handbook \| Developer.WordPress.org

<https://developer.wordpress.org/rest-api/glossary/>

[\[7\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=It%E2%80%99s%20really%20just%20a%20way,So%20you%20basically%20copy%20it)
[\[8\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=WordPress%2C%20you%20use%20your%20username,That%E2%80%99s%20generated%20for%20us)
[\[9\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=And%20then%20if%20I%20scroll,an%20admin%2C%20so%20that%E2%80%99s%20everything)
[\[11\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=editor%2C%20it%E2%80%99s%2C%20you%20know%2C%20all,endpoints%20are%20really%20running%20that)
[\[12\]](https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/#:~:text=that%20through%20an%20application%20password%2C,can%20this%20current%20user%20do)
WP REST API and Postman - Using Application Passwords - Brian Coords

<https://www.briancoords.com/wp-rest-api-and-postman-using-application-passwords/>

[\[19\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=To%20make%20this%20available%20via,when%20the%20API%20isn%E2%80%99t%20loaded)
[\[20\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=,)
[\[21\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=Namespacing)
[\[22\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=,%5D)
[\[23\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=function%20called%20,when%20the%20API%20isn%E2%80%99t%20loaded)
[\[24\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=define%20the%20HTTP%20methods%20allowed%2C,whether%20the%20field%20is%20required)
[\[25\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=By%20default%2C%20routes%20receive%20all,first%20parameter%20to%20your%20endpoint)
[\[26\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=%24parameters%20%3D%20%24request)
[\[28\]](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#:~:text=,%5D)
Adding Custom Endpoints -- REST API Handbook \| Developer.WordPress.org

<https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/>

[\[27\]](https://rudrastyh.com/woocommerce/create-product-programmatically.html#:~:text=It%20is%20the%20most%20correct,and%20it%20could%20cause%20bugs)
Create Product Programmatically in WooCommerce

<https://rudrastyh.com/woocommerce/create-product-programmatically.html>

[\[29\]](https://www.reddit.com/r/vibecoding/comments/1p4in9f/the_unspoken_hero_of_antigravity_browser/#:~:text=The%20unspoken%20hero%20of%20Antigravity%3A,automatic%20screenshots%20as%20reference)
The unspoken hero of Antigravity: Browser extension and automatic \...

<https://www.reddit.com/r/vibecoding/comments/1p4in9f/the_unspoken_hero_of_antigravity_browser/>
