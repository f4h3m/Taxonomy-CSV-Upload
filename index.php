<?php
/*
Plugin Name: Taxonomy CSV Upload
Description: Upload taxonomy terms from a CSV file.
Version: 1.0
Author: Fahem Ahmed
*/

// Add admin menu item
function taxonomy_csv_upload_menu() {
    add_management_page(
        'Taxonomy CSV Upload',
        'Taxonomy CSV Upload',
        'manage_options',
        'taxonomy-csv-upload',
        'taxonomy_csv_upload_page'
    );
}
add_action('admin_menu', 'taxonomy_csv_upload_menu');

// Admin page content
function taxonomy_csv_upload_page() {
    ?>
    <div class="wrap">
        <h1>Taxonomy CSV Upload</h1>

        <form method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th scope="row"><label>Taxonomy:</label></th>
                    <td>
                        <fieldset>
                            <?php
                            $taxonomies = get_taxonomies(array('public' => true), 'objects');
                            foreach ($taxonomies as $taxonomy) {
                                echo '<label>
                                        <input type="radio" name="taxonomy" value="' . $taxonomy->name . '" ' . checked('category', $taxonomy->name, false) . '> 
                                        ' . $taxonomy->label .
                                        '</label><br>'; // Display both label and name
                            }
                            ?>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="csv_file">CSV File:</label></th>
                    <td><input type="file" name="csv_file" id="csv_file" accept=".csv"></td>
                </tr>
            </table>

            <?php submit_button('Upload'); ?>
        </form>

        <?php
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
            process_csv_upload();
        }
        ?>
    </div>
    <?php
}

// CSV processing function (with parent term creation, image link storage, improved output, and header row ignored)
function process_csv_upload() {
    $csv_file = $_FILES['csv_file']['tmp_name'];
    $taxonomy = $_POST['taxonomy'];

    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        // Ignore the first row (header)
        fgetcsv($handle);

        // Store parent term IDs for later use
        $parent_term_ids = array();

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $term_name = $data[0];
            $parent_term_name = $data[1];
            $image_url = $data[2]; // Assuming third column is image URL

            // Basic sanitization
            $term_name = sanitize_text_field($term_name);
            $parent_term_name = sanitize_text_field($parent_term_name);
            $image_url = esc_url_raw($image_url); // Sanitize URL

            // Get or create parent term ID
            $parent_term_id = 0;
            if (!empty($parent_term_name)) {
                if (isset($parent_term_ids[$parent_term_name])) {
                    // Parent term was already created, use its ID
                    $parent_term_id = $parent_term_ids[$parent_term_name];
                } else {
                    $parent_term = term_exists($parent_term_name, $taxonomy);
                    if ($parent_term) {
                        $parent_term_id = $parent_term['term_id'];
                    } else {
                        // Create parent term if not found
                        $parent_term_id = wp_insert_term($parent_term_name, $taxonomy);
                        if (is_wp_error($parent_term_id)) {
                            echo "<p>Error creating parent term: " . $parent_term_id->get_error_message() . "</p>";
                        } else {
                            echo "<p>Created parent term: $parent_term_name</p>";
                            // Store the created parent term ID
                            $parent_term_ids[$parent_term_name] = $parent_term_id;
                        }
                    }
                }
            }

            if (term_exists($term_name, $taxonomy)) {
                $existing_term = get_term_by('name', $term_name, $taxonomy);
                $term_id = $existing_term->term_id;

                // Update existing term, including parent if it has changed
                if ($existing_term->parent != $parent_term_id) {
                    $args = array('parent' => $parent_term_id);
                    wp_update_term($term_id, $taxonomy, $args);
                }

                // Update category image link (if provided)
                if (!empty($image_url)) {
                    update_option('z_taxonomy_image' . $term_id, $image_url, false); // Store URL directly, matching "Categories Images"
                }

                if ($parent_term_id > 0) {
                    echo "<p>Updated term: <b>$term_name</b> (Parent: <b>$parent_term_name</b>)</p>";
                } else {
                    echo "<p>Updated term: <b>$term_name</b> (Parent removed)</p>";
                }

            } else {
                // Insert new term
                $term_id = wp_insert_term($term_name, $taxonomy, array('parent' => $parent_term_id));

                if (is_wp_error($term_id)) {
                    echo "<p>Error inserting term: " . $term_id->get_error_message() . "</p>";
                } else {
                    // Set category image link (if provided)
                    if (!empty($image_url)) {
                        update_option('z_taxonomy_image' . $term_id, $image_url, false); // Store URL directly, matching "Categories Images"
                    }

                    if ($parent_term_id > 0) {
                        echo "<p>Inserted term: <b>$term_name</b> (Parent: <b>$parent_term_name</b>)</p>";
                    } else {
                        echo "<p>Inserted term: <b>$term_name</b></p>";
                    }
                }
            }
        }
        fclose($handle);
    } else {
        echo "<p>Error opening CSV file.</p>";
    }
}