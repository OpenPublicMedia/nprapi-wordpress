<?php

/**
 * @file
 *
 * Defines a class for NPRML creation/transmission and retrieval/parsing
 * Unlike NPRAPI class, NPRAPIDrupal is drupal-specific
 */
require_once( dirname( __FILE__ ) . '/NPRAPI.php' );
require_once( dirname( __FILE__ ) . '/nprml.php' );

/**
 * Class NPRAPIWordpress
 */
class NPRAPIWordpress extends NPRAPI {

	/**
	 * Makes HTTP request to NPR API.
	 *
	 * @param array $params
	 *   Key/value pairs to be sent (within the request's query string).
	 *
	 *
	 * @param string $path
	 *   The path part of the request URL (i.e., https://example.com/PATH).
	 *
	 * @param string $base
	 *   The base URL of the request (i.e., HTTP://EXAMPLE.COM/path) with no trailing slash.
	 */
	function request( $params = [], $path = 'query', $base = self::NPRAPI_PULL_URL ) {

		$this->request->params = $params;
		$this->request->path = $path;
		$this->request->base = $base;

		$queries = [];
		foreach ( $this->request->params as $k => $v ) {
			$queries[] = "$k=$v";
		}
		$request_url = $this->request->base . '/' . $this->request->path . '?' . implode( '&', $queries );
		$this->request->request_url = $request_url;
		$this->query_by_url( $request_url );
	}

	/**
	 *
	 * Query a single url.  If there is not an API Key in the query string, append one, but otherwise just do a straight query
	 *
	 * @param string $url -- the full url to query.
	 */
	function query_by_url( $url ) {
		//check to see if the API key is included, if not, add the one from the options
		if ( !stristr( $url, 'apiKey=' ) ) {
			$url .= '&apiKey='. get_option( 'ds_npr_api_key' );
		}

		$this->request->request_url = $url;

		//fill out the $this->request->param array so we can know what params were sent
		$parsed_url = parse_url( $url );
		if ( !empty( $parsed_url['query'] ) ) {
			$params = explode( '&', $parsed_url['query'] );
			if ( !empty( $params ) ) {
				foreach ( $params as $p ){
					$attrs = explode( '=', $p );
					$this->request->param[ $attrs[0] ] = $attrs[1];
				}
			}
		}
		$response = wp_remote_get( $url );
		if ( !is_wp_error( $response ) ) {
			$this->response = $response;
			if ( $response['response']['code'] == self::NPRAPI_STATUS_OK ) {
				if ( $response['body'] ) {
					$this->xml = $response['body'];
				} else {
					$this->notices[] = __( 'No data available.' );
				}
			} else {
				nprstory_show_message( 'An error occurred pulling your story from the NPR API.  The API responded with message =' . $response['response']['message'], TRUE );
			}
		} else {
			$error_text = '';
			if ( !empty( $response->errors['http_request_failed'][0] ) ) {
				$error_text = '<br> HTTP Error response =  ' . $response->errors['http_request_failed'][0];
			}
			nprstory_show_message( 'Error pulling story for url=' . $url . $error_text, TRUE );
			nprstory_error_log( 'Error retrieving story for url=' . $url );
		}
	}

	/**
	 *
	 * This function will go through the list of stories in the object and check to see if there are updates
	 * available from the NPR API if the pubDate on the API is after the pubDate originally stored locally.
	 *
	 * @param bool $publish
	 * @return int|null $post_id or null
	 */
	function update_posts_from_stories( $publish = TRUE, $qnum = false ) {
		$pull_post_type = get_option( 'ds_npr_pull_post_type' );
		if ( empty( $pull_post_type ) ) {
			$pull_post_type = 'post';
		}
		$use_npr_layout = ( !empty( get_option( 'dp_npr_query_use_layout' ) ) ? TRUE : FALSE );

		$post_id = null;

		if ( !empty( $this->stories ) ) {
			$single_story = TRUE;
			if ( sizeof( $this->stories ) > 1 ) {
				$single_story = FALSE;
			}
			foreach ( $this->stories as $story ) {
				$exists = new WP_Query([
					'meta_key' => NPR_STORY_ID_META_KEY,
					'meta_value' => $story->id,
					'post_type' => $pull_post_type,
					'post_status' => 'any',
					'no_found_rows' => true
				]);

				// set the mod_date and pub_date to now so that for a new story we will fail the test below and do the update
				$post_mod_date = strtotime( date( 'Y-m-d H:i:s' ) );
				$post_pub_date = $post_mod_date;
				$cats = [];
				if ( $exists->posts ) {
					$existing = $exists->post;
					$post_id = $existing->ID;
					$existing_status = $exists->posts[0]->post_status;
					$post_mod_date_meta = get_post_meta( $existing->ID, NPR_LAST_MODIFIED_DATE_KEY );
					// to store the category ids of the existing post
					if ( !empty( $post_mod_date_meta[0] ) ) {
						$post_mod_date = strtotime( $post_mod_date_meta[0] );
					}
					$post_pub_date_meta = get_post_meta( $existing->ID, NPR_PUB_DATE_META_KEY );
					if ( !empty( $post_pub_date_meta[0] ) ) {
						$post_pub_date = strtotime( $post_pub_date_meta[0] );
					}
					// get ids of existing categories for post
					$cats = wp_get_post_categories( $post_id );
				} else {
					$existing = $existing_status = null;
				}

				$npr_has_layout = FALSE;
				$npr_has_video = FALSE;
				// get the "NPR layout" version if available and the "use rich layout" option checked in settings
				// if option is not checked, return the text with HTML
				$npr_layout = $this->get_body_with_layout( $story, $use_npr_layout );
				if ( !empty( $npr_layout['body'] ) ) {
					$story->body = $npr_layout['body'];
					$npr_has_layout = $npr_layout['has_layout'];
					$npr_has_video = $npr_layout['has_video'];
				}

				// add the transcript
				$story->body .= $this->get_transcript_body( $story );

				$story_date = new DateTime( $story->storyDate->value );
				$post_date = $story_date->format( 'Y-m-d H:i:s' );

				// set the story as draft, so we don't try ingesting it
				$args = [
					'post_title'	=> $story->title,
					'post_excerpt'	=> $story->teaser,
					'post_content'	=> $story->body,
					'post_status'	=> 'draft',
					'post_type'		=> $pull_post_type,
					'post_date'		=> $post_date
				];
				$wp_category_ids = [];
				$wp_category_id = "";
				if ( false !== $qnum ) {
					$args['tags_input'] = get_option( 'ds_npr_query_tags_' . $qnum );
					if ( $pull_post_type == 'post' ) {
						// Get Default category from options table and store in array for post_array
						$wp_category_id = intval( get_option( 'ds_npr_query_category_' . $qnum ) );
						$wp_category_ids[] = $wp_category_id;
					}
				} else {
					// Assign default category to new post
					if ( $existing === null ) {
						$wp_category_id = intval( get_option( 'default_category' ) );
						$wp_category_ids[] = $wp_category_id;
					}
				}
				if ( 0 < sizeof( $cats ) ) {
					// merge arrays and remove duplicate ids
					$wp_category_ids = array_unique( array_merge( $wp_category_ids, $cats ) );
				}
				// check the last modified date and pub date (sometimes the API just updates the pub date), if the story hasn't changed, just go on
				if ( $post_mod_date != strtotime( $story->lastModifiedDate->value ) || $post_pub_date != strtotime( $story->pubDate->value ) ) {
					$by_line = '';
					$byline_link = '';
					$multi_by_line = '';
					// continue to save single byline into npr_byline as is, but also set multi to false
					if ( isset( $story->byline->name->value ) ) { // fails if there are multiple bylines or no bylines
						$by_line = $story->byline->name->value;
						$multi_by_line = 0; // only single author, set multi to false
						if ( !empty( $story->byline->link ) ) {
							$links = $story->byline->link;
							if ( is_string( $links ) ) {
								$byline_link = $links;
							} elseif ( is_array( $links ) ) {
								foreach ( $links as $link ) {
									if ( empty( $link->type ) ) {
										continue;
									}
									if ( 'html' === $link->type ) {
										$byline_link = $link->value;
									}
								}
							} elseif ( $links instanceof NPRMLElement && ! empty( $links->value ) ) {
								$byline_link = $links->value;
							}
						}
					}

					// construct delimited string if there are multiple bylines
					if ( !empty( $story->byline ) ) {
						$i = 0;
						foreach ( (array)$story->byline as $single ) {
							if ( !empty( $single->name->value ) ) {
								if ( $i == 0 ) {
									$multi_by_line .= $single->name->value; // builds multi byline string without delimiter on first pass
								} else {
									$multi_by_line .= '|' . $single->name->value; // builds multi byline string with delimiter
								}
								$by_line = $single->name->value; // overwrites so as to save just the last byline for previous single byline themes
							}
							if ( !empty( $single->link ) ) {
								foreach( (array)$single->link as $link ) {
									if ( empty( $link->type ) ) {
										continue;
									}
									if ( 'html' === $link->type ) {
										$byline_link = $link->value; // overwrites so as to save just the last byline link for previous single byline themes
										$multi_by_line .= '~' . $link->value; // builds multi byline string links
									}
								}
							}
							$i++;
						}
					}
					// set the meta RETRIEVED so when we publish the post, we don't try ingesting it
					$metas = [
						NPR_STORY_ID_META_KEY		  => $story->id,
						NPR_API_LINK_META_KEY		  => $story->link['api']->value,
						NPR_HTML_LINK_META_KEY		  => $story->link['html']->value,
						// NPR_SHORT_LINK_META_KEY	  => $story->link['short']->value,
						NPR_STORY_CONTENT_META_KEY	  => $story->body,
						NPR_BYLINE_META_KEY			  => $by_line,
						NPR_BYLINE_LINK_META_KEY	  => $byline_link,
						NPR_MULTI_BYLINE_META_KEY	  => $multi_by_line,
						NPR_RETRIEVED_STORY_META_KEY  => 1,
						NPR_PUB_DATE_META_KEY		  => $story->pubDate->value,
						NPR_STORY_DATE_MEATA_KEY	  => $story->storyDate->value,
						NPR_LAST_MODIFIED_DATE_KEY	  => $story->lastModifiedDate->value,
						NPR_STORY_HAS_LAYOUT_META_KEY => $npr_has_layout,
						NPR_STORY_HAS_VIDEO_META_KEY  => $npr_has_video
					];
					// get audio
					if ( isset( $story->audio ) ) {
						$mp3_array = $m3u_array = [];
						foreach ( (array)$story->audio as $n => $audio ) {
							if ( !empty( $audio->format->mp3['mp3'] ) && $audio->permissions->download->allow == 'true' ) {
								if ( $audio->format->mp3['mp3']->type == 'mp3' ) {
									$mp3_array[] = $audio->format->mp3['mp3']->value;
								}
								if ( $audio->format->mp3['m3u']->type == 'm3u' ) {
									$m3u_array[] = $audio->format->mp3['m3u']->value;
								}
							}
						}
						$metas[NPR_AUDIO_META_KEY] = implode( ',', $mp3_array );
						$metas[NPR_AUDIO_M3U_META_KEY] = implode( ',', $m3u_array );
					}
					if ( $existing ) {
						$created = false;
						$args['ID'] = $existing->ID;
					} else {
						$created = true;
					}

					/**
					 * Filters the $args passed to wp_insert_post()
					 *
					 * Allow a site to modify the $args passed to wp_insert_post() prior to post being inserted.
					 *
					 * @since 1.7
					 *
					 * @param array $args Parameters passed to wp_insert_post()
					 * @param int $post_id Post ID or NULL if no post ID.
					 * @param NPRMLEntity $story Story object created during import
					 * @param bool $created true if not pre-existing, false otherwise
					 */

					if ( $npr_has_layout ) {
						// keep WP from stripping content from NPR posts
						kses_remove_filters();
					}

					$args = apply_filters( 'npr_pre_insert_post', $args, $post_id, $story, $created );
					$post_id = wp_insert_post( $args );
					wp_set_post_terms( $post_id, $wp_category_ids, 'category', true );

					if ( $npr_has_layout ) {
						// re-enable the built-in content stripping
						kses_init_filters();
					}

					// now that we have an id, we can add images
					// this is the way WP seems to do it, but we couldn't call media_sideload_image or media_ because that returned only the URL
					// for the attachment, and we want to be able to set the primary image, so we had to use this method to get the attachment ID.
					if ( !empty( $story->image ) && is_array( $story->image ) && count( $story->image ) ) {

						// are there any images saved for this post, probably on update, but no sense looking of the post didn't already exist
						if ( $existing ) {
							$image_args = [
								'order'=> 'ASC',
								'post_mime_type' => 'image',
								'post_parent' => $post_id,
								'post_status' => null,
								'post_type' => 'attachment',
								'post_date'	=> $post_date
							];
							$attached_images = get_children( $image_args );
						}
						foreach ( (array)$story->image as $image ) {

							// only sideload the primary image if using the npr layout
							if ( ( $image->type != 'primary' ) && $npr_has_layout ) {
								continue;
							}
							$image_url = '';
							// check the <enlargement> and then the crops, in this order "enlargement", "standard"  if they don't exist, just get the image->src
							if ( !empty( $image->enlargement ) ) {
								$image_url = $image->enlargement->src;
							}
							if ( !empty( $image->crop ) && is_array( $image->crop ) ) {
								foreach ( $image->crop as $crop ) {
									if ( empty( $crop->type ) ) {
										continue;
									}
									if ( 'enlargement' === $crop->type ) {
										$image_url = $crop->src;
										// sometimes enlargements are much larger than needed
										if ( ( 1500 < $crop->height ) || ( 2000 < $crop->width ) ) {
											// if there's no querystring already, add s=6 which media.npr.org resizes to a usable but large size
											if ( strpos( $image_url, "?" ) === false ) {
												$image_url .= "?s=6";
											}
										}
									}
								}
								if ( empty( $image_url ) ) {
									foreach ( $image->crop as $crop ) {
										if ( empty( $crop->type ) ) {
											continue;
										}
										if ( 'standard' === $crop->type ) {
											$image_url = $crop->src;
										}
									}
								}
							}

							if ( empty( $image_url ) && !empty( $image->src ) ) {
								$image_url = $image->src;
							}
							nprstory_error_log( 'Got image from: ' . $image_url );

							$imagep_url_parse = parse_url( $image_url );
							$imagep_url_parts = pathinfo( $imagep_url_parse['path'] );
							if ( !empty( $attached_images ) ) {
								foreach( $attached_images as $att_image ) {
									// see if the filename is very similar
									$attach_url = wp_get_original_image_url( $att_image->ID );
									$attach_url_parse = parse_url( $attach_url );
									$attach_url_parts = pathinfo( $attach_url_parse['path'] );

									// so if the already attached image name is part of the name of the file
									// coming in, ignore the new/temp file, it's probably the same
									if ( strtolower( $attach_url_parts['filename'] ) === strtolower( $imagep_url_parts['filename'] ) ) {
										continue 2;
									}
								}
							}

							// Download file to temp location
							$tmp = download_url( $image_url );

							// Set variables for storage
							$file_array['name'] = $imagep_url_parts['basename'];
							$file_array['tmp_name'] = $tmp;

							$file_OK = TRUE;
							// If error storing temporarily, unlink
							if ( is_wp_error( $tmp ) ) {
								@unlink( $file_array['tmp_name'] );
								$file_array['tmp_name'] = '';
								$file_OK = FALSE;
							}

							// do the validation and storage stuff
							require_once( ABSPATH . 'wp-admin/includes/image.php' ); // needed for wp_read_image_metadata used by media_handle_sideload during cron
							$image_upload_id = media_handle_sideload( $file_array, $post_id, $image->title->value );
							// If error storing permanently, unlink
							if ( is_wp_error( $image_upload_id ) ) {
								@unlink( $file_array['tmp_name'] );
								$file_OK = FALSE;
							}

							//set the primary image
							if ( $image->type == 'primary' && $file_OK ) {
								$current_thumbnail_id = get_post_thumbnail_id( $post_id );
								if ( !empty( $current_thumbnail_id ) && $current_thumbnail_id != $image_upload_id ) {
									delete_post_thumbnail( $post_id );
								}
								set_post_thumbnail( $post_id, $image_upload_id );
								//get any image meta data and attatch it to the image post
								if ( NPR_IMAGE_CREDIT_META_KEY === NPR_IMAGE_AGENCY_META_KEY ) {
									$image_credits = [ $image->producer->value, $image->provider->value ];
									$image_metas = [
										NPR_IMAGE_CREDIT_META_KEY => implode( ' | ', $image_credits ),
										NPR_IMAGE_CAPTION_META_KEY => $image->caption->value
									];
								} else {
									$image_metas = [
										NPR_IMAGE_CREDIT_META_KEY => $image->producer->value,
										NPR_IMAGE_AGENCY_META_KEY => $image->provider->value,
										NPR_IMAGE_CAPTION_META_KEY => $image->caption->value
									];
								}
								foreach ( $image_metas as $k => $v ) {
									update_post_meta( $image_upload_id, $k, $v );
								}
							}
						}
					}

					/**
					 * Filters the post meta before series of update_post_meta() calls
					 *
					 * Allow a site to modify the post meta values prior to
					 * passing each element via update_post_meta().
					 *
					 * @since 1.7
					 *
					 * @param array $metas Array of key/value pairs to be updated
					 * @param int $post_id Post ID or NULL if no post ID.
					 * @param NPRMLEntity $story Story object created during import
					 * @param bool $created true if not pre-existing, false otherwise
					 */
					$metas = apply_filters( 'npr_pre_update_post_metas', $metas, $post_id, $story, $created );

					foreach ( $metas as $k => $v ) {
						update_post_meta( $post_id, $k, $v );
					}

					$args = [
						'post_title'	=> $story->title,
						'post_content'	=> $story->body,
						'post_excerpt'	=> $story->teaser,
						'post_type'		=> $pull_post_type,
						'ID'			=> $post_id,
						'post_date'		=> $post_date
					];

					//set author
					if ( ! empty( $by_line ) ) {
						$userQuery = new WP_User_Query([
							'search' => trim( $by_line ),
							'search_columns' => [ 'nickname' ]
						]);

						$user_results = $userQuery->get_results();
						if ( count( $user_results ) == 1 && isset( $user_results[0]->data->ID ) ) {
							$args['post_author'] = $user_results[0]->data->ID;
						}
					}

					//now set the status
					if ( !$existing ) {
						if ( $publish ) {
							$args['post_status'] = 'publish';
						} else {
							$args['post_status'] = 'draft';
						}
					} else {
						//if the post existed, save its status
						$args['post_status'] = $existing_status;
					}

					/**
					 * Filters the $args passed to wp_insert_post() used to update
					 *
					 * Allow a site to modify the $args passed to wp_insert_post() prior to post being updated.
					 *
					 * @since 1.7
					 *
					 * @param array $args Parameters passed to wp_insert_post()
					 * @param int $post_id Post ID or NULL if no post ID.
					 * @param NPRMLEntity $story Story object created during import
					 */

					if ( $npr_has_layout ) {
						// keep WP from stripping content from NPR posts
						kses_remove_filters();
					}

					$args = apply_filters( 'npr_pre_update_post', $args, $post_id, $story );
					$post_id = wp_insert_post( $args );

					if ( $npr_has_layout ) {
						// re-enable content stripping
						kses_init_filters();
					}
				}

				// set categories for story
				$category_ids = [];
				$category_ids = array_merge( $category_ids, $wp_category_ids );
				if ( isset( $story->parent ) ) {
					if ( is_array( $story->parent ) ) {
						foreach ( $story->parent as $parent ) {
							if ( isset( $parent->type ) && 'category' === $parent->type ) {

								/**
								 * Filters term name prior to lookup of terms
								 *
								 * Allow a site to modify the terms looked-up before adding them to list of categories.
								 *
								 * @since 1.7
								 *
								 * @param string $term_name Name of term
								 * @param int $post_id Post ID or NULL if no post ID.
								 * @param NPRMLEntity $story Story object created during import
								 */
								$term_name = apply_filters( 'npr_resolve_category_term', $parent->title->value, $post_id, $story );
								$category_id = get_cat_ID( $term_name );

								if ( !empty( $category_id ) ) {
									$category_ids[] = $category_id;
								}
							}
						}
					} elseif ( isset( $story->parent->type ) && $story->parent->type === 'category' ) {
						/**
						* Filters term name prior to lookup of terms
						*
						* Allow a site to modify the terms looked-up before adding them to list of categories.
						*
						* @since 1.7
						*
						* @param string $term_name Name of term
						* @param int $post_id Post ID or NULL if no post ID.
						* @param NPRMLEntity $story Story object created during import
						*/
						$term_name = apply_filters( 'npr_resolve_category_term', $story->parent->title->value, $post_id, $story );
						$category_id = get_cat_ID( $term_name );
						if ( !empty( $category_id ) ) {
							$category_ids[] = $category_id;
						}
					}
				}

				/**
				* Filters category_ids prior to setting assigning to the post.
				*
				* Allow a site to modify category IDs before assigning to the post.
				*
				* @since 1.7
				*
				* @param int[] $category_ids Array of Category IDs to assign to post identified by $post_id
				* @param int $post_id Post ID or NULL if no post ID.
				* @param NPRMLEntity $story Story object created during import
				*/
				$category_ids = apply_filters( 'npr_pre_set_post_categories', $category_ids, $post_id, $story );
				if ( 0 < count( $category_ids ) && is_integer( $post_id ) ) {
					wp_set_post_categories( $post_id, $category_ids );
				}

				// If the Co-Authors Plus plugin is installed, use the bylines from the API output to set guest authors
				if ( function_exists( 'get_coauthors' ) ) {
					global $coauthors_plus;
					$coauthor_terms = [];
					if ( !empty( $multi_by_line ) ) {
						$bylines = explode( '|', $multi_by_line );
						foreach ( $bylines as $bl ) {
							$blx = explode( '~', $bl );
							$search_author = $coauthors_plus->search_authors( $blx[0], [] );
							if ( !empty( $search_author ) ) {
								reset( $search_author );
								$coauthor_terms[] = key( $search_author );
							}
						}
					} elseif ( !empty( $by_line ) ) {
						$search_author = $coauthors_plus->search_authors( $by_line, [] );
						if ( !empty( $search_author ) ) {
							reset( $search_author );
							$coauthor_terms[] = key( $search_author );
						}
					}
					wp_set_post_terms( $post_id, $coauthor_terms, $coauthors_plus->coauthor_taxonomy, false );
				}
			}
			if ( $single_story ) {
				return isset( $post_id ) ? $post_id : 0;
			}
		}
		return null;
	}

	/**
	 * Create NPRML from wordpress post.
	 *
	 * @param object $post
	 *   A wordpress post.
	 *
	 * @return string
	 *   An NPRML string.
	 */
	function create_NPRML( $post ) {
		// using some old helper code
		return nprstory_to_nprml( $post );
	}

	/**
	 * This function will send the push request to the NPR API to add/update a story.
	 *
	 * @see NPRAPI::send_request()
	 *
	 * @param string $nprml
	 * @param int $post_ID
	 */
	function send_request ( $nprml, $post_ID ) {
		$error_text = '';
		$org_id = get_option( 'ds_npr_api_org_id' );
		if ( !empty( $org_id ) ) {
			$args = [
				'orgId'  => $org_id,
				'apiKey' => get_option( 'ds_npr_api_key' )
			];
			$args = apply_filters( 'npr_pre_article_push', $args, $post_ID );
			$url = add_query_arg( $args, get_option( 'ds_npr_api_push_url' ) . '/story' );

			nprstory_error_log( 'Sending nprml = ' . $nprml );

			$result = wp_remote_post( $url, ['body' => $nprml ] );
			if ( !is_wp_error( $result ) ) {
				if ( $result['response']['code'] == 200 ) {
					$body = wp_remote_retrieve_body( $result );
					if ( $body ) {
						$response_xml = simplexml_load_string( $body );
						$npr_story_id = (string)$response_xml->list->story['id'];
						update_post_meta( $post_ID, NPR_STORY_ID_META_KEY, $npr_story_id );
					} else {
						error_log( 'Error returned from NPR Story API with status code 200 OK but failed wp_remote_retrieve_body: ' . print_r( $result, true ) ); // debug use
					}
				} else {
					$error_text = '';
					if ( !empty( $result['response']['message'] ) ) {
						$error_text = 'Error pushing story with post_id = ' . $post_ID . ' for url=' . $url . ' HTTP Error response =  ' . $result['response']['message'];
					}
					$body = wp_remote_retrieve_body( $result );

					if ( $body ) {
						$response_xml = simplexml_load_string( $body );
						$error_text .= '  API Error Message = ' . $response_xml->message->text;
					}
					error_log( 'Error returned from NPR Story API with status code other than 200 OK: ' . $error_text ); // debug use
				}
			} else {
				$error_text = 'WP_Error returned when sending story with post_ID ' . $post_ID . ' for url ' . $url . ' to NPR Story API:' . $result->get_error_message();
				error_log( $error_text ); // debug use
			}
		} else {
			$error_text = 'OrgID was not set when tried to push post_ID ' . $post_ID . ' to the NPR Story API.';
			error_log( $error_text ); // debug use
		}

		// Add errors to the post that you just tried to push
		if ( !empty( $error_text ) ) {
			update_post_meta( $post_ID, NPR_PUSH_STORY_ERROR, $error_text );
		} else {
			delete_post_meta( $post_ID, NPR_PUSH_STORY_ERROR );
		}
	}

	/**
	 * wp_remote_request supports sending a custom method, so the cURL code has been removed
	 *
	 * @param  $api_id
	 */
	function send_delete( $api_id ) {
		$args = [
			'orgId'  => get_option( 'ds_npr_api_org_id' ),
			'apiKey' => get_option( 'ds_npr_api_key' ),
			'id' => $api_id
		];
		$args = apply_filters( 'npr_pre_article_delete', $args );
		$url = add_query_arg( $args, get_option( 'ds_npr_api_push_url' ) . '/story' );

		$result = wp_remote_request( $url, [ 'method' => 'DELETE' ] );
		$body = wp_remote_retrieve_body( $result );
	}

	/**
	 * Retrieve a transcript of an article from the NPR API using a provided link
	 *
	 * @param object $transcript
	 *   An NPR Element object
	 *
	 * @return string
	 *   The transcript formatted as a string
	 */
	function get_transcript_from_link( $transcript ) {
		$transcript_body = '';
		foreach ( (array)$transcript->link as $link ) {
			if ( !isset( $link->type ) || 'api' !== $link->type ) {
				continue;
			}
			$response = wp_remote_get( $link->value );
			if ( is_wp_error( $response ) ) {
				/**
				 * @var WP_Error $response
				 */
				$code = $response->get_error_code();
				$message = $response->get_error_message();
				$message = sprintf( 'Error requesting story transcript via API URL: %s (%s [%d])', $link->value, $message,  $code );
				error_log( $message );
				continue;
			}
			$body_xml = simplexml_load_string( $response[ 'body' ] );
			if ( !empty( $body_xml->paragraph ) ) {
				$transcript_body .= '<div class="npr-transcript"><p><strong>Transcript :</strong></p>';
				foreach ( $body_xml->paragraph as $paragraph ) {
					$transcript_body .= $this->add_paragraph_tag( $paragraph ) . "\n";
				}
				$transcript_body .= '</div>';
			}
		}
		return $transcript_body;
	}
	/**
	 * This function will check a story to see if there are transcripts that should go with it, if there are
	 * we'll return the transcript as one big string with Transcript at the top and each paragraph separated by <p>
	 *
	 * @param object $story
	 * @return string
	 */
	function get_transcript_body( $story ) {
		$transcript_body = "";
		if ( !empty( $story->transcript ) ) {
			if ( is_array( $story->transcript ) ) {
				foreach ( $story->transcript as $transcript ) {
					if ( empty( $transcript->link ) ) {
						continue;
					}
					$transcript_body .= $this->get_transcript_from_link( $transcript );
				}
			} else {
				$transcript_body .= $this->get_transcript_from_link( $story->transcript );
			}
		}
		return $transcript_body;
	}

	/**
	 * Format and return a paragraph of text from an associated NPR API article
	 * This function checks if the text is already wrapped in an HTML element (e.g. <h3>, <div>, etc.)
	 * If not, the return text will be wrapped in a <p> tag
	 *
	 * @param string $p
	 *   A string of text
	 *
	 * @return string
	 *   A formatted string of text
	 */
	function add_paragraph_tag( $p ) {
		$output = '';
		if ( preg_match( '/^<[a-zA-Z0-9 \="\-_\']+>.+<[a-zA-Z0-9\/]+>$/', $p ) ) {
			if ( preg_match( '/^<(a href|em|strong)/', $p ) ) {
				$output = '<p>' . $p . '</p>';
			} else {
				if ( strpos( $p, '<div class="storyMajorUpdateDate">' ) !== false ) {
					$output = $p;
				}
				$output = $p;
			}
		} else {
			if ( strpos( $p, '<div class="fullattribution">' ) !== false ) {
				$output = '<p>' . str_replace( '<div class="fullattribution">', '</p><div class="fullattribution">', $p );
			} else {
				$output = '<p>' . $p . '</p>';
			}
		}
		return $output;
	}

	/**
	 * Convert an NPRElement object into an array
	 *
	 * @param object $story
	 *   An NPR Element object
	 *
	 * @param string $element
	 *   The story elements to parse
	 *
	 * @return array
	 *   An ID-based array of elements
	 */
	function parse_story_elements( $story, $element ) {
		$output = [];
		if ( isset( $story->{$element} ) ) {
			$element_array = [];
			if ( isset( $story->{$element}->id ) ) {
				$element_array[] = $story->{$element};
			} else {
				// sometimes there are multiple objects
				foreach ( (array)$story->{$element} as $elem ) {
					if ( isset( $elem->id ) ) {
						$element_array[] = $elem;
					}
				}
			}
			foreach ( $element_array as $elem ) {
				$output[ $elem->id ] = (array)$elem;
			}
		}
		return $output;
	}

	/**
	 * Extract HTML links from NPRML output
	 *
	 * @param object $link
	 *   An NPR Element object
	 *
	 * @return string
	 *   The HTML link
	 */
	function link_extract( $links ) {
		$output = '';
		if ( !empty( $links ) ) :
			if ( is_string( $links ) ) :
				$output = $links;
			elseif ( is_array( $links ) ) :
				foreach ( $links as $link ) :
					if ( empty( $link->type ) ) {
						continue;
					}
					if ( 'html' === $link->type ) :
						$output = $link->value;
					endif;
				endforeach;
			elseif ( $links instanceof NPRMLElement && !empty( $links->value ) ) :
				$output = $links->value;
			endif;
		endif;
		return $output;
	}

	/**
	 * This function will check a story to see if it has a layout object, if there is
	 * we'll format the body with any images, externalAssets, or htmlAssets inserted in the order they are in the layout
	 * and return an array of the transformed body and flags for what sort of elements are returned
	 *
	 * @param NPRMLEntity $story Story object created during import
	 * @return array with reconstructed body and flags describing returned elements
	 */
	function get_body_with_layout( $story, $layout ) {
		$returnary = [ 'body' => FALSE, 'has_layout' => FALSE, 'has_image' => FALSE, 'has_video' => FALSE, 'has_external' => FALSE, 'has_slideshow' => FALSE ];
		$body_with_layout = "";
		$use_npr_featured = ( !empty( get_option( 'dp_npr_query_use_featured' ) ) ? TRUE : FALSE );
		if ( $layout ) {
			if ( !empty( $story->layout->storytext ) ) {
				// simplify the arrangement of the storytext object
				$layoutarry = [];
				foreach( $story->layout->storytext as $type => $elements ) {
					if ( $elements === null ) {
						continue;
					}
                    if ( !is_array( $elements ) ) {
						$elements = [ $elements ];
					}
					foreach ( $elements as $element ) {
						$num = $element->num;
						$reference = $element->refId;
						if ( $type == 'text' ) {
							// only paragraphs don't have a refId, they use num instead
							$reference = $element->paragraphNum;
						}
						$layoutarry[ (int)$num ] = [ 'type' => $type, 'reference' => $reference ];
					}
				}
				ksort( $layoutarry );
				$returnary['has_layout'] = TRUE;

				$paragraphs = [];
				$num = 1;
				foreach ( $story->textWithHtml->paragraphs as $paragraph ) {
					$partext = (string)$paragraph->value;
					$paragraphs[ $num ] = $partext;
					$num++;
				}

				$storyimages = [];
				if ( isset( $story->image ) ) {
					$storyimages_array = [];
					if ( isset( $story->image->id ) ) {
						$storyimages_array[] = $story->image;
					} else {
						// sometimes there are multiple objects
						foreach ( (array)$story->image as $stryimage ) {
							if ( isset( $stryimage->id ) ) {
								$storyimages_array[] = $stryimage;
							}
						}
					}
					foreach ( $storyimages_array as $image ) {
						$image_url = FALSE;
						$is_portrait = FALSE;
						if ( !empty( $image->enlargement ) ) {
							$image_url = $image->enlargement->src;
						}
						if ( !empty( $image->crop ) ) {
							if ( !is_array( $image->crop ) ) {
								$cropobj = $image->crop;
								unset( $image->crop );
								$image->crop = [ $cropobj ];
							}
							foreach ( $image->crop as $crop ) {
								if ( empty( $crop->primary ) ) {
									continue;
								}
								$image_url = $crop->src;
								if ( $crop->type == 'custom' && ( (int)$crop->height > (int)$crop->width ) ) {
									$is_portrait = TRUE;
								}
								break;
							}
						}
						if ( empty( $image_url ) && !empty( $image->src ) ) {
							$image_url = $image->src;
						}
						// add resizing to any npr-hosted image
						if ( strpos( $image_url, 'media.npr.org' ) ) {
							// remove any existing querystring
							if ( strpos( $image_url, '?' ) ) {
								$image_url = substr( $image_url, 0, strpos( $image_url, '?' ) );
							}
							$image_url .= ( !$is_portrait ? '?s=6' : '?s=12' );
						}
						$storyimages[ $image->id ] = (array)$image;
						$storyimages[ $image->id ]['image_url'] = $image_url;
						$storyimages[ $image->id ]['is_portrait'] = $is_portrait;
					}
				}

				$htmlAssets = [];
				if ( isset( $story->htmlAsset ) ) {
					if ( isset( $story->htmlAsset->id ) ) {
						$htmlAssets[ $story->htmlAsset->id ] = $story->htmlAsset->value;
					} else {
						// sometimes there are multiple objects
						foreach ( (array)$story->htmlAsset as $hasset ) {
							if ( isset( $hasset->id ) ) {
								$htmlAssets[ $hasset->id ] = $hasset->value;
							}
						}
					}
				}

				$externalAssets = $this->parse_story_elements( $story, 'externalAsset' );
				$multimedia = $this->parse_story_elements( $story, 'multimedia' );
				$members = $this->parse_story_elements( $story, 'member' );
				$collection = $this->parse_story_elements( $story, 'collection' );
				$container = $this->parse_story_elements( $story, 'container' );
				$listText = $this->parse_story_elements( $story, 'listText' );
				$related = $this->parse_story_elements( $story, 'relatedLink' );

				foreach ( $layoutarry as $ordernum => $element ) {
					$reference = $element['reference'];
					switch ( $element['type'] ) {
						case 'text' :
							if ( !empty( $paragraphs[ $reference ] ) ) {
								$body_with_layout .= $this->add_paragraph_tag( $paragraphs[ $reference ] ) . "\n";
							}
							break;
						case 'staticHtml' :
							if ( !empty( $htmlAssets[ $reference ] ) ) {
								$body_with_layout .= $htmlAssets[ $reference ] . "\n\n";
								$returnary['has_external'] = TRUE;
								if ( strpos( $htmlAssets[ $reference ], 'jwplayer.com' ) ) {
									$returnary['has_video'] = TRUE;
								}
							}
							break;
						case 'externalAsset' :
							if ( !empty( $externalAssets[ $reference ] ) ) {
								$figclass = "wp-block-embed";
								if ( !empty( (string)$externalAssets[ $reference ]['type'] ) && strtolower( (string)$externalAssets[ $reference ]['type'] ) == 'youtube') {
									$returnary['has_video'] = TRUE;
									$figclass .= " is-type-video";
								}
								$fightml = "<figure class=\"$figclass\"><div class=\"wp-block-embed__wrapper\">";
								$fightml .=  "\n" . $externalAssets[$reference]['url'] . "\n";
								$figcaption = '';
								if ( !empty( (string)$externalAssets[ $reference ]['credit'] ) || !empty( (string)$externalAssets[ $reference ]['caption'] ) ) {
									if ( !empty( trim( (string)$externalAssets[ $reference ]['credit'] ) ) ) {
										$figcaption .= " <cite>" . trim( (string)$externalAssets[ $reference ]['credit'] ) . "</cite>";
									}
									if ( !empty( (string)$externalAssets[ $reference ]['caption'] ) ) {
										$figcaption .= trim( (string)$externalAssets[ $reference ]['caption'] );
									}
									$figcaption = !empty( $figcaption ) ? "<figcaption>$figcaption</figcaption>" : "";
								}
								$fightml .= "</div>$figcaption</figure>\n";
								$body_with_layout .= $fightml;
							}
							break;
						case 'multimedia' :
							if ( !empty( $multimedia[ $reference ] ) ) {
								// check permissions
								$perms = $multimedia[ $reference ]['permissions'];
								if ( $perms->embed->allow != false ) {
									$fightml = "<figure class=\"wp-block-embed is-type-video\"><div class=\"wp-block-embed__wrapper\">";
									$returnary['has_video'] = TRUE;
									$fightml .= "<div style=\"padding-bottom: 56.25%; position:relative; height:0;\"><iframe src=\"https://www.npr.org/embedded-video?storyId=" . (int)$story->id . "&mediaId=$reference&jwMediaType=music\" frameborder=\"0\" scrolling=\"no\" style=\"position:absolute; top:0; left:0; width:100%; height:100%;\" marginwidth=\"0\" marginheight=\"0\"></iframe></div>";
									$figcaption = '';
									if ( !empty( (string)$multimedia[ $reference ]['credit'] ) || !empty( (string)$multimedia[ $reference ]['caption'] ) ) {
										if (!empty( trim( (string)$multimedia[ $reference ]['credit'] ) ) ) {
											$figcaption .= " <cite>" . trim( (string)$multimedia[ $reference ]['credit'] ) . "</cite>";
										}
										if ( !empty( (string)$multimedia[ $reference ]['caption'] ) ) {
											$figcaption .= trim( (string)$multimedia[ $reference ]['caption'] );
										}
										$figcaption = ( !empty( $figcaption ) ? "<figcaption>$figcaption</figcaption>" : "" );
									}
									$fightml .= "</div>$figcaption</figure>\n";
									$body_with_layout .= $fightml;
								}
							}
							break;
						case 'container' :
							if ( !empty( $container[ $reference ] ) ) {
								$thiscon = $container[ $reference ];
								$figclass = 'npr-container';
								if ( !empty( $thiscon['colSpan']->value ) && $thiscon['colSpan']->value < 4 ) {
									$figclass .= ' npr-container-col-1';
								}
								$fightml = "<figure class=\"wp-block-embed $figclass\"><div class=\"wp-block-embed__wrapper\">";
								if ( !empty( $thiscon['title']->value ) ) {
									$fightml .= "<h2>" . $thiscon['title']->value . "</h2>";
								}
								if ( !empty( $thiscon['introText']->value ) ) {
									$fightml .= "<p>" . $thiscon['introText']->value . "</p>";
								}
								if ( !empty( $thiscon['link']->refId ) ) {
									if ( !empty( $related[ $thiscon['link']->refId ] ) ) {
										$fightml .= '<p><a href="' . $this->link_extract( $related[ $thiscon['link']->refId ]['link'] ) . '">' . $related[ $thiscon['link']->refId ]['caption']->value . '</a></p>';
									}
								}
								if ( !empty( $thiscon['listText']->refId ) ) {
									if ( !empty( $listText[ $thiscon['listText']->refId ] ) ) {
										foreach ( $listText[ $thiscon['listText']->refId ]['paragraphs'] as $lparagraph ) {
											$fightml .= $lparagraph->value;
										}
									}
								}
								$fightml .= "</div></figure>\n";
								$body_with_layout .= $fightml;
							}
							break;
						case 'list' :
							if ( !empty( $collection[ $reference ] ) ) {
								$thiscol = $collection[ $reference ];
								$fightml = '';
								if ( strtolower( $thiscol['displayType'] ) == "slideshow" ) {
									$returnary['has_slideshow'] = TRUE;
									$caption = '';
									if ( !empty( $json['title'] ) ) {
										$caption .= '<h3>' . $json['title'] . '</h3>';
									}
									if ( !empty( $json['intro'] ) ) {
										$caption .= '<p>' . $json['intro'] . '</p>';
									}
									$fightml .= '<figure class="wp-block-image"><div class="splide"><div class="splide__track"><ul class="splide__list">';
									foreach ( $thiscol['member'] as $cmem ) {
										if ( !empty( $members[ $cmem->refId ] ) ) {
											$thismem = $members[ $cmem->refId ];
											if ( !empty( $thismem['image'] ) ) {
												$thisimg = $storyimages[ $thismem['image']->refId ];
												$image_url = $thisimg['image_url'];
												$credits = [];
												$full_credits = '';
												if ( !empty( $thisimg['producer']->value ) ) {
													$credits[] = $thisimg['producer']->value;
												}
												if ( !empty( $thisimg['provider']->value ) ) {
													$credits[] = $thisimg['provider']->value;
												}
												if ( !empty( $thisimg['copyright']->value ) ) {
													$credits[] = $thisimg['copyright']->value;
												}
												if ( !empty( $credits ) ) {
													$full_credits = ' (' . implode( ' | ', $credits ) . ')';
												}
												$link_text = str_replace( '"', "'", $thisimg['title']->value . $full_credits );
												foreach ( $thisimg['crop'] as $crop ) {
													if ( $crop->type == $thismem['image']->crop ) {
														$image_url = $crop->src;
													}
												}
												$fightml .= '<li class="splide__slide"><a href="' . esc_url( $image_url ) . '" target="_blank"><img data-splide-lazy="' . esc_url( $image_url ) . '" alt="' . esc_attr( $link_text ) . '"></a><div>' . nprstory_esc_html( $link_text ) . '</div></li>';
											}
										}
									}
									$fightml .= '</div></div></ul>';
									if ( !empty( $caption ) ) {
										$fightml .= '<figcaption>' . $caption . '</figcaption>';
									}
									$fightml .= '</figure>';
								} elseif ( strtolower( $thiscol['displayType'] ) == "simple story" ) {
									$fightml .= '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper"><h2>' . $thiscol['title']->value . '</h2><ul>';
									foreach ( $thiscol['member'] as $cmem ) {
										$c_member = $members[ $cmem->refId ];
										$fightml .= '<li><h3>';
										if ( !empty( $c_member['link'] ) ) {
											$fightml .= '<a href="' . $c_member['link']->value . '" target="_blank">';
										}
										$fightml .= $c_member['title']->value;
										if ( !empty( $c_member['link'] ) ) {
											$fightml .= '</a>';
										}
										$fightml .= '</h3>';
										if ( !empty( $c_member['image'] ) ) {
											$c_member_image = $storyimages[ $c_member['image']->refId ];
											$fightml .= '<img src="' . $c_member_image['image_url'] . '" alt="' . $c_member_image['title']->value . '" loading="lazy" />';
										}
										$fightml .= $c_member['introText']->value . '</li>';
									}
									$fightml .= '</ul></div></figure>';
								}
								$body_with_layout .= $fightml;
							}
							break;
						default :
							if ( !empty( $storyimages[ $reference ] ) ) {
								$figclass = "wp-block-image size-large";
								$thisimg = $storyimages[ $reference ];
								if ( $thisimg['type'] == 'primary' && $use_npr_featured ) {
									break;
								}
								$fightml = ( !empty( (string)$thisimg['image_url'] ) ? '<img src="' . (string)$thisimg['image_url'] . '"' : '' );
								if ( !empty( $thisimg['is_portrait'] ) ) {
									$figclass .= ' alignright';
									$fightml .= " width=200";
								}
								$thiscaption = ( !empty( trim( (string)$thisimg['caption'] ) ) ? trim( (string)$thisimg['caption'] ) : '' );
								$fightml .= ( !empty( $fightml ) && !empty( $thiscaption ) ? ' alt="' . str_replace( '"', '\'', strip_tags( $thiscaption ) ) . '"' : '' );
								$fightml .= ( !empty( $fightml ) ? '>' : '' );
								$figcaption = ( !empty( $fightml ) && !empty( $thiscaption ) ? $thiscaption  : '' );
								$cites = '';
								foreach ( [ 'producer', 'provider', 'copyright' ] as $item ) {
									$thisitem = trim( (string)$thisimg[ $item ] );
									if ( !empty( $thisitem ) ) {
										$cites .= ( !empty( $cites ) ? ' | ' . $thisitem : $thisitem );
									}
								}
								$cites = ( !empty( $cites ) ? " <cite>$cites</cite>" : '' );
								$thiscaption .= $cites;
								$figcaption = ( !empty( $fightml ) && !empty( $thiscaption ) ? "<figcaption>$thiscaption</figcaption>"  : '' );
								$fightml .= ( !empty( $fightml ) && !empty( $figcaption ) ? $figcaption : '' );
								$body_with_layout .= ( !empty( $fightml ) ? "<figure class=\"$figclass\">$fightml</figure>\n\n" : '' );
								// make sure it doesn't get reused;
								unset( $storyimages[ $reference ] );
							}
							break;
					}
				}
			} else {
				foreach ( $story->textWithHtml->paragraphs as $paragraph ) {
					$body_with_layout .= $this->add_paragraph_tag( (string)$paragraph->value ) . "\n";
				}
			}
		} else {
			foreach ( $story->textWithHtml->paragraphs as $paragraph ) {
				$body_with_layout .= $this->add_paragraph_tag( (string)$paragraph->value ) . "\n";
			}
		}
		if ( isset( $story->correction ) ) {
			$body_with_layout .= '<p class="correction"><strong>' . $story->correction->correctionTitle->value . ': <em>' .
				wp_date( get_option( 'date_format' ), strtotime( $story->correction->correctionDate->value ) ) .
				'</em></strong><br />' . strip_tags( $story->correction->correctionText->value ) .
			'</p>';
		}
		if ( isset( $story->audio ) ) {
			$audio_file = '';
			foreach ( (array)$story->audio as $n => $audio ) {
				if ( $audio->type == 'primary' ) {
					if ( $audio->permissions->download->allow == 'true' ) {
						if ( $audio->format->mp3['mp3']->type == 'mp3' ) {
							$audio_file = $audio->format->mp3['mp3']->value;
						}
					} elseif ( $audio->permissions->stream->allow == 'true' ) {
						if ( !empty( $audio->format->mp3->value ) ) {
							if ( $audio->format->mp3['m3u']->type == 'm3u' ) {
								$response = wp_remote_get( $audio->format->mp3['m3u']->value );
								if ( is_wp_error( $response ) ) {
									/**
									 * @var WP_Error $response
									 */
									$code = $response->get_error_code();
									$message = $response->get_error_message();
									$message = sprintf( 'Error requesting audio m3u via API URL: %s (%s [%d])', $audio->format->mp3['m3u']->value, $message,  $code );
									error_log( $message );
									continue;
								}
								$audio_file = $response['body'];
							}
						} elseif ( !empty( $audio->format->mediastream->value ) ) {
							$audio_file = str_replace( 'rtmp://flash.npr.org/ondemand/mp3:', 'https://ondemand.npr.org/', $audio->format->mediastream->value );
						} elseif ( !empty( $audio->format->hlsOnDemand->value ) ) {
							$audio_file = str_replace( [ 'https://ondemandhls.npr.org/nprhls/', '/master.m3u8' ], [ 'https://ondemand.npr.org/anon.npr-mp3', '.mp3' ], $audio->format->hlsOnDemand->value );
						}
					}
				}
			}
			if ( !empty( $audio_file ) ) {
				$body_with_layout = '[audio mp3="'.$audio_file.'"][/audio]' . "\n" . $body_with_layout;
			}
		}
		if ( $returnary['has_slideshow'] ) {
			$body_with_layout = '<link rel="stylesheet" href="' . NPRSTORY_PLUGIN_URL . 'assets/css/splide.min.css" />' .
				$body_with_layout .
				'<script src="' . NPRSTORY_PLUGIN_URL . 'assets/js/splide.min.js"></script>' .
				'<script src="' . NPRSTORY_PLUGIN_URL . 'assets/js/splide-settings.js"></script>';
		}
		$returnary['body'] = nprstory_esc_html( $body_with_layout );
		return $returnary;
	}
}
