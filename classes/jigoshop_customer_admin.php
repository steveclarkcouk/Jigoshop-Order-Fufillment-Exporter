<?php
/* 
* JIGOSHOP Fufillment Export
*
* Works by adding custom meta to an order i.e. sent_for_procurment and 
* date_sent_for_procurement these will only obviously update on success of FTP
* Add Meta Box to order page with Procurement upload date
*/


class Jigoshop_Fufillment_Order_Admin extends  Jigoshop_Fufillment_Order {

		var $types = array('email','ftp');
		var $version = '1';
		var $orders = null;
		var $errors = array();
	
		public function __construct() {

			

			// Load the plugin when Jigoshop is enabled
			if ( in_array( 'jigoshop/jigoshop.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
				add_action('init', array($this, 'install'));
				add_action('init', array($this, 'load_all_hooks'));
			}
			


			parent::__construct();
		}

		public function install() {
			if(!get_option('jigoshop_order_exporter_version')) {
				update_option( 'jigoshop_order_exporter_version', $this->version );
			}
			if(get_option('jigoshop_order_exporter_version') != $this->version) {
				// -- Out Of Date Need To Update
			}
		}

		public function load_all_hooks() {	
			
			add_action( 'admin_print_styles', array( $this, 'add_styles' ) );
			add_action( 'admin_print_scripts', array( $this, 'add_scripts' ) );
			add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
			add_action('admin_menu', array( $this, 'add_menu'));
			add_action( 'admin_init', array( $this, 'register_plugin_settings') );
			//add_action( 'admin_init', array( $this, 'page_init' ) );
			//add_action( 'admin_footer', array( $this,'customer_order_lightbox') );
			//add_action('wp_ajax_view_customer_order_history', array( $this,'view_customer_order_history_callback'));

			if(isset($_POST['process_fufillment_csv'])) {
				$this->doCSV();
				if(get_option('jigoshop_order_exporter_type') == 'ftp') {
					
					$this->ftpCSVFile();
				}
				if(get_option('jigoshop_order_exporter_type') == 'email') {
					$this->doEmail();
				}
				
			}


			
		}

		/**
		 * Add the styles
		 */
		public function add_styles() {
			wp_register_style( 'jigoshop_order_exporter_css', plugins_url( 'assets/plugin.css' , __FILE__ ));
			wp_enqueue_style(  'jigoshop_order_exporter_css');
		}

		/**
		 * Add the scripts
		 */
		public function add_scripts() {
			wp_register_script( 'jigoshop_order_exporter_js', plugins_url( 'assets/js.js' , __FILE__ ), array('jquery'), '1', true );
			wp_enqueue_script(  'jigoshop_order_exporter_js');
		}

		public function register_plugin_settings() {
		     register_setting( 'jigoshop_order_exporter-settings-group', 'jigoshop_order_exporter_type' );
		     register_setting( 'jigoshop_order_exporter-settings-group', 'jigoshop_order_exporter_email' );
			 register_setting( 'jigoshop_order_exporter-settings-group', 'jigoshop_order_exporter_ftp_host' );
  			 register_setting( 'jigoshop_order_exporter-settings-group', 'jigoshop_order_exporter_ftp_user' );
  			 register_setting( 'jigoshop_order_exporter-settings-group', 'jigoshop_order_exporter_ftp_password' );
  			 register_setting( 'jigoshop_order_exporter-settings-group', 'jigoshop_order_exporter_ftp_port' );
  			 register_setting( 'jigoshop_order_exporter-settings-group', 'jigoshop_order_exporter_send_time' );
		}

		public function add_box() {
			add_meta_box( 'jigoshop-fufillment-box', __( 'Fufillment Export Status', 'jigoshop-delivery-notes' ), array( $this, 'create_box_content' ), 'shop_order', 'side', 'default');
		}

		public function create_box_content() {
			global $post;
		
			if(!$post) return;

			//print_r($_POST);

			if($_GET['reset_fufilment'] == 1) {
				$this->changeOrderFufillmentStatus();
			}

			$status = (get_post_meta($post->ID,'procurement_uploaded', true)) ? '<span class="sent">Sent</span>' : '<span class="not-sent">Not Sent</span>' ;
			$time = (get_post_meta($post->ID,'procurement_timestamp', true)) ? date('d/F/Y', get_post_meta($post->ID,'procurement_timestamp', true)) : 'In Queue to send' ;

			?><table class="form-table">
                <tr>
                    <td>
                    	<p>Fufillment Status: <?php echo $status; ?></p>
                    	<p>Fufillment Date: <?php echo $time; ?> </p>
                    	
                    	<p><?php if(get_post_meta($post->ID,'procurement_uploaded', true)) : ?>
                    		<a class="button button-primary" href="<?php echo admin_url( 'post.php?post=' . $post->ID . '&reset_fufilment=1&action=edit', 'http' ); ?>">Set to unfufilled</a>
                 	    <?php endif; ?></p>
                    </td>
                </tr>
     	   </table><?php
		}

		/**
		 * Add the menu
		 */
		public function add_menu() {
			add_submenu_page('edit.php?post_type=shop_order', __('Customer Order Export Options', 'jigoshop-order-export'), __('Order Fufillment Export', 'jigoshop-customer-info'), 'manage_options', 'jigoshop_order_export_settings', array($this, 'create_menu_content') );
		}

		/**
		 * Create the menu content
		 */
		public function create_menu_content() {

			// Check the user capabilities
			if (!current_user_can('manage_options')) {
				wp_die( __('You do not have sufficient permissions to access this page.') );
			}

			// Show the fields
			?><div class="wrap">
			<h2><?php _e('Jigoshop Fufillment Export', 'jigoshop_order_exporter'); ?></h2>

			<?php if($this->errors) : ?>
				<div class="errors">
					<?php foreach($errors as $error) : ?>
						<p class="error"><?php echo $error; ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div id="todays_orders">
				<h3><?php _e('Orders Awaiting Jigoshop Fufillment Export', 'jjigoshop_order_exporter'); ?></h3>
				<?php $orders = $this->getTodaysProcessedOrders(); ?>

				<table cellpadding="5" width="100%">
					<tr style="background:#000;color:#fff">
						<th>Order Number</th>
						<th>Customer Name</th>
						<th>Billing Address</th>
						<th>Shipping Address</th>
						<th>Contact Info</th>	
						<th>Items</th>
						<th>Cost</th>
					</tr>

					<?php if($orders) : foreach($orders as $order) : $_order = new jigoshop_order($order->ID); ?>
					<tr style="font-size:90%;background:#fff">
						<td width="5%">#<?php echo $_order->id; ?></td>
						<td width="5%"><?php echo $_order->billing_first_name; ?> <?php echo $order->billing_last_name; ?></td>
						<td width="20%"><?php echo $_order->formatted_billing_address; ?></td>
						<td width="20%"> <?php echo $_order->formatted_shipping_address; ?></td>
						<td width="10%">
							Email:<br/> <?php echo $_order->billing_email ?><br/>
							Phone:<br/> <?php echo $_order->billing_phone ?></td>	
						<td width="35%">
							
							<?php if (sizeof($_order->items)>0 && isset($_order->items[0]['id'])) foreach ($_order->items as $item_no => $item) : ?>
								
								<?php 
								// -- Not Sure If We Need This
								if (isset($item['variation_id']) && $item['variation_id'] > 0) {
									$_product = new jigoshop_product_variation( $item['variation_id'] );
			                        if(is_array($item['variation'])) {
			                            $_product->set_variation_attributes($item['variation']);
			                        }
			                    } else {
									$_product = new jigoshop_product( $item['id'] );
			                    } 
			                    ?>

		                   
		                    <?php echo $item['qty']; ?> x  <?php echo $item['name']; ?><br/>

		                <?php endforeach; ?>
						</td>
						<td width="5%"><?php echo $_order->order_total; ?></td>
					</tr>
					<?php endforeach; else: ?>
					<tr style="font-size:90%;background:#fff">
						<td colspan="7">No Orders to fufill</td>
					</tr>
					<?php endif; ?>
					
				</table>
				<?php if($orders) : ?>
					<form method="post" style="margin:2em 0;"><input class="fufill_me_baby button button-primary" type="submit" name="process_fufillment_csv" value="Process CSV Fufilment" /></form>
				<?php endif; ?>
			</div>

			<div id="plugin_settings">
			<h3>Fufillment Settings</h3>
				<form method="post" action="options.php">
				    <?php settings_fields( 'jigoshop_order_exporter-settings-group' ); ?>
				    <?php do_settings_sections( 'jigoshop_order_exporter-settings-group' ); ?>
				    <table class="form-table">
				        <tr valign="top">
				        <th scope="row">Type Of Delivery</th>
					        <td>
					        	<select name="jigoshop_order_exporter_type" id="jigoshop_order_id">
					        		<?php foreach($this->types as $type) : ?>
					        			<?php if(get_option('jigoshop_order_exporter_type') == $type) : ?>
					        				<option selected="selected" value="<?php echo $type; ?>"><?php echo $type; ?></option>
					        			<?php else : ?>
					        				<option value="<?php echo $type; ?>"><?php echo $type; ?></option>
					        			<?php endif; ?>
					        		<?php endforeach; ?>
					        	</select>
					        </td>
				    	</tr>
				    </table>

				    <?php $type = get_option('jigoshop_order_exporter_type') ? get_option('jigoshop_order_exporter_type') : 'email'; ?>

				     <table class="form-table" id="jigoshop_order_email" <?php if($type == 'ftp') : ?>style="display:none;"<?php endif; ?>>
				        <tr valign="top">
				       	 	<th scope="row">Email To Send CSV File To:</th>
				       	 	<td><input type="text" name="jigoshop_order_exporter_email" value="<?php echo get_option('jigoshop_order_exporter_email'); ?>" /></td>
				        </tr>
				      </table>

				      <table class="form-table"  id="jigoshop_order_ftp" <?php if($type == 'email') : ?>style="display:none;"<?php endif; ?>>
				        <tr valign="top">
				       	 	<th scope="row">FTP Host:</th>
				       	 	<td><input type="text" name="jigoshop_order_exporter_ftp_host" value="<?php echo get_option('jigoshop_order_exporter_ftp_host'); ?>" /></td>
				        </tr>

				        <tr valign="top">
				       	 	<th scope="row">FTP Port:</th>
				       	 	<td><input type="text" name="jigoshop_order_exporter_ftp_port" value="<?php echo get_option('jigoshop_order_exporter_ftp_port'); ?>" /></td>
				        </tr>

				        <tr valign="top">
				       	 	<th scope="row">FTP Username:</th>
				       	 	<td><input type="text" name="jigoshop_order_exporter_ftp_user" value="<?php echo get_option('jigoshop_order_exporter_ftp_user'); ?>" /></td>
				        </tr>

				        <tr valign="top">
				       	 	<th scope="row">FTP Password:</th>
				       	 	<td><input type="text" name="jigoshop_order_exporter_ftp_password" value="<?php echo get_option('jigoshop_order_exporter_ftp_password'); ?>" /></td>
				        </tr>

				        <!-- <tr valign="top">
				       	 	<th scope="row">Time Of Day To Send:</th>
				       	 	<td><input type="text" name="jigoshop_order_exporter_send_time" value="<?php echo get_option('jigoshop_order_exporter_send_time'); ?>" /></td>
				        </tr> -->

				    </table>
				    
				    <?php submit_button(); ?>

				</form>
			</div>

		<?php
		}

		
		private function getTodaysProcessedOrders() {
				/*$start_date =  strtotime(date('Ymd', strtotime( date('Ym', current_time('timestamp')).'01' )));
				$end_date = strtotime(date('Ymd', current_time('timestamp')));*/
				$args = array(
						'numberposts'      => -1,
						'orderby'          => 'post_date',
						'order'            => 'ASC',
						'post_type'        => 'shop_order',
						'post_status'      => 'publish' ,
						'suppress_filters' => 0,
						'tax_query'        => array(
							array(
								'taxonomy' => 'shop_order_status',
								'terms'    => array('processing'),
								'field'    => 'slug',
								'operator' => 'IN'
							)
						),
						'meta_key'=> 'procurement_uploaded',
						'meta_value' =>  true,
						'meta_compare' => '!='
				);
				return get_posts( $args );
		}


		private function ftpCSVFile() {
			// set up basic connection
			$ftp_server = get_option('jigoshop_order_exporter_ftp_host');
			$conn_id = ftp_connect($ftp_server ); 
			$orders = $this->getTodaysProcessedOrders();

			// login with username and password
			$login_result = ftp_login($conn_id, get_option('jigoshop_order_exporter_ftp_user'), get_option('jigoshop_order_exporter_ftp_password')); 

			// check connection
			if ((!$conn_id) || (!$login_result)) { 
			    $this->errors[] = "FTP connection has failed!";
			    $this->errors[] = "Attempted to connect to $ftp_server for user $ftp_user_name"; 
			    exit; 
			} 

			// upload the file
			$upload = ftp_put($conn_id, 'skinny_project_orders-' . date('d-F-Y') . '.csv', plugin_dir_path( __FILE__ ) . 'list.csv' , FTP_BINARY); 

			// check upload status
			if (!$upload) { 
			     $this->errors[] = "FTP upload has failed!";
			} else {
				
				// -- Loop Through Orders Attaching Meta
				if($orders) {
					foreach($orders as $order) {
						update_post_meta( $order->ID, 'procurement_uploaded', true );
						update_post_meta( $order->ID, 'procurement_timestamp',  time() );
					}
				}
			    // -- Delete the old CSV file
			}

			// close the FTP stream 
			ftp_close($conn_id); 

		}

		public function doCSV() {
			
			//echo 'DOING CSV';
			//print_r( $this->getTodaysProcessedOrders() );
			ini_set("auto_detect_line_endings", true);
			if( $this->getTodaysProcessedOrders() ) {

				// -- Create THE CSV From The Cart
			
				$values[] = array(
					'DATE',
					'ORDER REF',
					'CONTACT NAME',
					'COMPANY ADDRESS1',
					'ADDRESS2',
					'ADDRESS3',
					'TOWN',
					'COUNTY',
					'POSTCODE',
					'COUNTRY',
					'COUNTRY CODE',
					'PRODUCT CODE',
					'PRODUCT NAME',
					'QTY',
					'CUSTOMER REFERENCE',
					'TELEPHONE',
					'MOBILENO',
					'COMMENTS',
					'EMAIL',
					'SHIPPERS REF',
					'DELIVERY TYPE',
					'ORDER ORIGIN',
					'CUSTOMS VALUE',
					'DELIVERY INSTRUCTIONS'	
				);

				foreach($this->getTodaysProcessedOrders() as $order) {

					$_order = new jigoshop_order($order->ID);
					
					
					foreach($_order->items as $item) {


						// -- Lets Get Product Specific Stuff Done Here
						if (isset($item['variation_id']) && $item['variation_id'] > 0) {
							$_product = new jigoshop_product_variation( $item['variation_id'] );
	                        if(is_array($item['variation'])) {
	                            $_product->set_variation_attributes($item['variation']);
	                        }
	                    } else {
							$_product = new jigoshop_product( $item['id'] );
	                    }

	                    // -- Has SKU
	                    $product_id = ($_product->sku) ? $_product->sku : $_product->ID;

	                    // -- Is A Variant
	                    $variant_html = null;
                        if (isset($_product->variation_data)) :
                            $variant_html = jigoshop_get_formatted_variation( $_product->variation_data, true );
                        elseif ( isset($item['variation']) && is_array($item['variation']) ) :
                            foreach( $item['variation'] as $var ) {
                                $variant_html .= "{$var['name']} : {$var['value']}";
                            }
                        else :
                            $variant_html = '';
                        endif;
                       
	                    // -- Has Customisation
                        $custom = null; $custom_html = null;

	                     if ( ! empty( $item['customization'] ) ) {
                           $custom = $item['customization'];
                           $label = apply_filters( 'jigoshop_customized_product_label', __(' Personal: ','jigoshop') );                          
                        }

                        if($custom) {
                        	$custom_html = "Customisation\r\n\r\n" . $label . ': ' . $custom;
                        }
                         


						$values[] = array(
							date('d F Y', strtotime($order->post_date)),
							$order->ID,
							$_order->billing_first_name . ' ' . $order->billing_last_name,
							$_order->shipping_address_1,
							$_order->shipping_address_2,
							'',
							$_order->shipping_city,
							$_order->shipping_state,
							$_order->shipping_postcode,
							$_order->shipping_country,
							jigoshop_countries::$countries[$_order->shipping_country],
							$product_id,
							$item['name'],
							$item['qty'],
							$order->user_id . '#' . $order->ID,   // USER ID & ORDER ID
							$_order->billing_phone,
							'',
							$variant_html . "\r\n\r\n" . $custom_html,
							$_order->billing_email,
							'N/A',
							'N/A',
							'Website',
							($item['cost'] * $item['qty']),
							$_order->customer_note	
						);

					}
				}

				

				$fp = fopen( plugin_dir_path( __FILE__ ) . 'list.csv', 'w');
				foreach ($values as $fields) {
				    fputcsv($fp, $fields);
				}
				fclose($fp);

				$this->sendAdministratorCSV();

				//print_r($data_to_export);
			}

			// -- Send FTP File To Server
			
		}

		public function changeOrderFufillmentStatus() {
			global $post;
			update_post_meta( $post->ID, 'procurement_uploaded', false );
			update_post_meta( $post->ID, 'procurement_timestamp', '' );
		}

		public function doEmail() {
			$email = get_option('jigoshop_order_exporter_email');
			if($email) {

			   $attachments = plugin_dir_path( __FILE__ ) . 'list.csv';
			   $headers = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>' . "\r\n";
			   $x_mail = wp_mail($email, 'Fufillment Export For' . get_bloginfo('name'), 'Please find attached CSV file of orders that require fufillment', $headers, $attachments );
			   $this->sendAdministratorCSV();

			   // -- Update Orders Meta
			   $orders = $this->getTodaysProcessedOrders();
			   if($orders) {
					foreach($orders as $order) {
						update_post_meta( $order->ID, 'procurement_uploaded', true );
						update_post_meta( $order->ID, 'procurement_timestamp', time() );
					}
				}
			} else {
				$this->errors[] = 'No Address has been set to forward this CSV on';
			}
		}

		public function sendAdministratorCSV() {
			$attachments = plugin_dir_path( __FILE__ ) . 'list.csv';
			$headers = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>' . "\r\n";
			$y_mail = wp_mail(get_bloginfo('admin_email') , 'Fufillment Export For' . get_bloginfo('name'), 'Please find attached CSV file of orders that require fufillment', $headers, $attachments );
		}


	}



?>