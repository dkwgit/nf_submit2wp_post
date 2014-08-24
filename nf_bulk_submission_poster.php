<?php
/**
 * @package NF_Submission_Poster
 * @version 0.1
 */
/*
Plugin Name: NF_Submission_Poster
Description: Add templatized bulk conversion of Ninja Form submissions into posts
Author: David Wright
Version: 0.1
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
    exit;

//set following to true to enable both PHP debugging output and some output for function arguments below
define("DEBUG_NF_Submission_Poster",FALSE);

//When true, display all errors that occur.
if (defined("DEBUG_NF_Submission_Poster")) {
    ini_set('display_errors', 'On');
    error_reporting(E_ALL);
}


/**
 * This function adds to a bulk admin action that allows taking waiting Ninja Forms
 * submission(s) and converting them to WP posts per a template that maps form fields to post content.
 *
 * Code for this was borrowed from Ninja forms code, version 2.7.6 and slightly adapted 
 *
 * @since 0.1
 * @return void
 */
function bulk_admin_footer() {
    global $post_type;
    
    if ( ! is_admin() )
        return false;

    if( $post_type == 'nf_sub' && isset ( $_REQUEST['post_status'] ) && $_REQUEST['post_status'] == 'all' ) {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function() {
                jQuery('<option>').val('postit').text('<?php _e('Convert to Posts')?>').appendTo("select[name='action']");
                jQuery('<option>').val('postit').text('<?php _e('Convert to Posts')?>').appendTo("select[name='action2']");
        <?php
                if ( ( isset ( $_POST['action'] ) && $_POST['action'] == 'postit' ) || ( isset ( $_POST['action2'] ) && $_POST['action2'] == 'postit' ) ) {
                    ?>
                    setInterval(function(){
                        jQuery( "select[name='action'" ).val( '-1' );
                        jQuery( "select[name='action2'" ).val( '-1' );
                        jQuery( '#posts-filter' ).submit();
                    },5000);
                    <?php
                }
                ?>
            });
        </script>
        <?php
    }
}

//add the bulk admin action
add_action( 'admin_footer-edit.php', 'bulk_admin_footer' );
//listen for user requests of the action
add_action( 'load-edit.php', 'listen_for_bulk_submission_post' );

/*
 * Here is an example of a template mapping Ninja Forms submissions to WP post-content
 * The array key is the id of the Ninja Forms form we are targeting.
 * The values in {}, are the names of Nina Forms fields.  (We use the admin_label of the form field as the name, so that
 * that label needs to be filled out for this to work).
 *
 * When posts are created from submissions, the submission values are looked up by field name and the field name replaced by the
 * submission value.
 */
$post_templates = array( 
    '2' => array(
        'form_title' => 'Create a Book Review',
        'post_name' => '{Author}-{Book Title}',
        'post_title' => '{Book Title}, by {Author}',
        'post_content' => '[star rating="{Rating}"]<br/>{Holdings}<br/><br/>{Hook}<br/><br/>{Middle}<br/><br/>{Closing}',
        'post_author' => '{user_id}',
        'post_status' => 'pending',
        'tags_input' => '{Genre}'
    )
); 

/**
 * Function that takes the submitted values from a Ninja Forms submission,
 * and populates a post-template using (perhaps just some of) those values.
 * 
 * @param array $submission the submitted values of an individual form submission
 * @param array $template a template that shows how to substitute Ninja Form field submitted values into
 *                        a WP post entry
 * @param array $field_data form field meta-data retrieved from Ninja Form
 * @param mixed $user_id  this is the user_id of the person who made a submission, it can be null
 * @since 0.1
 * @return array a copy of the post template with submission values substituted into it
 */
function fill_out_post_template($submission, $template, $field_data, $user_id = NULL)
{
    if (FALSE && defined("DEBUG_NF_Submission_Poster")) {
        var_export($user_id);
	var_export($submission);
        var_export($template);
        var_export($field_data);
        
        die();
    }
    
    $template_copy = array();
    foreach($template as $key => $value) {
	if (FALSE && defined("DEBUG_NF_Submission_Poster")) {
                    var_export($key);
                    var_export($value);
                    die();
        }
        if (is_string($value)) {
	    if ($user_id !== NULL) {
                $value= str_replace('{user_id}',$user_id,$value);
            }
            $matches = array();            
            $matchReturn = preg_match_all('(\{[^}]+\})',$value, $matches,PREG_OFFSET_CAPTURE);
            if ($matchReturn !== FALSE && $matchReturn >= 1) {
                $matches = $matches[0];
                $newValue = do_matches_substitution($value, $matches, $submission, $field_data);

                if (!is_null($newValue)) {
                    $template_copy[$key] = $newValue;
                }
            } else {
                $template_copy[$key] = $value;
            }
        } else {
            $template_copy[$key] = $value;
        }
    }
    return $template_copy;
}

/**
 * Function that takes one string item from the post template (an item being one sub-entry, such as 'post-content'),
 * and resolves any {formfield} placeholders in the template item to the corresponding named form field submission values
 * from the Ninja Forms submission.
 * 
 * @param string $itemValue the value of a subItem in the post template
 * @param array $matches matching information looking for {formFieldName} placeholders in the item
 * @param array $submission_data submitted Ninja Form data
 * @param array $field_data form field meta-data retrieved from Ninja Form
 * @since 0.1
 * @return string the item with substitutions having been made
 */
function do_matches_substitution($itemValue, $matches, $submission_data, $field_data)
{
    if (FALSE && defined("DEBUG_NF_Submission_Poster")) {
        //var_export($itemValue);
        //var_export($matches);
        var_export($submission_data);
        var_export($field_data);
        die();
    }

    $newValue = $itemValue;
    foreach($matches as $match) {
        $fieldName = substr($match[0],1,strlen($match[0])-2);
        $stillInString = strpos($newValue, "{$fieldName}");


	if (FALSE && defined("DEBUG_NF_Submission_Poster")) {
            var_export($itemValue);    
	    var_export($newValue);    
	    var_export($match);
            var_export($match[0]);
            var_export($fieldName);
            var_export($stillInString);
            var_export($field_data);
            die();	
	}

        if ($stillInString !== FALSE) {
            $field_id = get_field_id_for_field($fieldName, $field_data);
            if (!is_null($field_id)) {
                $fieldData = $submission_data[$field_id];
                if (is_string($fieldData)) {
                	;
                } elseif (is_array($fieldData)) {
 			$fieldData = implode(',',$fieldData);
                        if (substr($fieldData,0,1) == ',') {
				$fieldData = substr($fieldData,1);
			}
                }
                $newValue = str_replace('{' . $fieldName  . '}',$fieldData,$newValue);
            }
        } 
    }

    return $newValue;
}

/**
 * Function to find the field id of a named field in the Ninja forms field metadata for a Ninja form.
 * We use the field's admin label as its name, so that must be filled out in order to mapped named fields
 * to field ids.  The submission data uses field ids.
 * 
 * @param string $fieldName   fieldName in a template item, that looks like {fieldName} in the item string.
 * @param array $field_data form field meta-data retrieved from Ninja Form
 * @since 0.1
 * @return mixed the id of the field, if found, otherwise NULL
 */
function get_field_id_for_field($fieldName, $field_data)
{
    if (FALSE && defined("DEBUG_NF_Submission_Poster")) {
        var_export($fieldName);
        var_export($field_data);
        die();
    }

    $field_id = NULL;
    foreach($field_data as $key => $field) {
        $data = $field['data'];
	
        if (array_key_exists('admin_label',$data) && strtolower($data['admin_label']) == strtolower($fieldName)) {
            $field_id = $field['id'];
            break;
        }
    }

    return $field_id;
}

/**
 * Function to create a WP post structure from a filled in template
 * 
 * @param array $filled_template  template that has been filled with values from the Ninja Forms submission.
 * @since 0.1
 * @return array structure containing the data needed to create a WP post
 */
function create_post($filled_template)
{
    $post_data = array();

    $post_data['post_status'] = $filled_template['post_status'];
    $post_data['post_title'] = $filled_template['post_title'];
    $post_data['post_type'] = 'post';
    $post_data['post_content'] = $filled_template['post_content'];
    $post_data['post_author'] =  $filled_template['post_author'];
    $post_data['tags_input'] = $filled_template['tags_input'];

    return $post_data;
}    

/**
 * Function that is called when an admin user bulk converts submission(s) to posts
 * the function loops over the select submissions and maps them to posts via a post template for the form that the submission
 * comes from.
 * 
 * @since 0.1
 * @return void
 */
function listen_for_bulk_submission_post()
{
    global $post_templates;
    if ( isset ( $_REQUEST['action'] ) && $_REQUEST['action'] == 'postit' ) {
        foreach( $_REQUEST['post']  as $item) {
            $sub = Ninja_Forms()->sub($item);
            $form_fields = ninja_forms_get_fields_by_form_id( $sub->form_id );
            if (!is_null($form_fields) && array_key_exists( $sub->form_id, $post_templates)) {
		$user_id = $sub->user_id;
                $filled_template = fill_out_post_template($sub->fields, $post_templates[$sub->form_id], $form_fields, $user_id);
                if (!is_null($filled_template)) {
                    $post = create_post($filled_template);
                    
                    if (FALSE && defined("DEBUG_NF_Submission_Poster")) {
                        var_export($post);
                        die();
                    }

                    if (!is_null($post)) {
                        wp_insert_post($post);
                    }
                }
            }
        }
    }
}