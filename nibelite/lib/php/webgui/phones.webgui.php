<?php   //      PROJECT:        nibelung
        //      MODULE:         Phones & Compatibility CMS class
        //      $Id: phones.webgui.php 159 2008-02-19 11:20:55Z misha $

include_once 'simple.webgui.php'; 

class CMSPhones extends CMS {

	function CMSPhones( $allow_delete = 0 ) {
		$prefix						= 'phones';

		$table						= 'models';

		$fields = array(
			'brand_id'				=> '0',
			'id'					=> '0'
		);

		$this->CMS( $prefix, $table, $fields, $allow_delete );

		$this->tasks['edit']				= 'return $this->edit();';
		$this->tasks['update']				= '$status=$this->update();return $this->edit();';
	}
  
	function show() {
		global $TPL, $status;

		$row_template_name				= $this->prefix . '_row';
		$empty_template_name				= $this->prefix . '_empty';
		$table_template_name				= $this->prefix . '_table';

		$row_template_entity				= $TPL[$row_template_name];
		$empty_template_entity				= $TPL[$empty_template_name];
		$table_template_entity				= $TPL[$table_template_name];

		$rows_data					= '';
		$filter_data					= '';

		$select_models_query				= "
			SELECT
				b.name AS brand_name, m.name AS model_name, m.id AS model_id
			FROM
				brands AS b
				JOIN models AS m ON ( m.brand_id = b.id )
			ORDER BY
				b.name, m.name
		";

		$select_models_data				= db_get( $select_models_query );

		$select_useragents_query			= "
			SELECT
				model_id AS model_id, agent as useragent_name
			FROM
				useragents
		";

		$select_useragents_data				= db_get( $select_useragents_query );

		$useragents					= array();

		if ( $select_useragents_data ) {
			foreach ( $select_useragents_data as $select_useragent_item ) {
				$useragent_model_id			= $select_useragent_item['model_id'];
				$useragent_name				= $select_useragent_item['useragent_name'];
					
				$useragents[$useragent_model_id][]	= $useragent_name;
			}
		}

		if ( $select_models_data ) {

			foreach ( $select_models_data as $select_model_item ) {
				$row_data = array(
					'script'			=> $_SERVER['SCRIPT_NAME']
				);

				foreach ( $select_model_item as $field => $value ) {
					$row_data[$field]		= htmlspecialchars( $value );
				}
				
				$model_id				= $select_model_item['model_id'];
				$row_data['id']				= $model_id;
				
				$model_useragents			= '';

				if ( array_key_exists( $model_id, $useragents ) and is_array( $useragents[$model_id] ) ) {
					$model_useragents		= implode( '<br/>', $useragents[$model_id] );
				}

				$row_data['user_agent']			= $model_useragents;

				$rows_data				.= template( $row_template_entity, $row_data );
			}
		}
		else {
			$empty_template_contents = array(
				'script'				=> $_SERVER['SCRIPT_NAME']
			);

			$rows_data					= template( $empty_template_entity, $empty_template_contents);
		}

		$table_template_contents = array(
			'rows'						=> $rows_data,
			'filter'					=> $filter_data,
			'script'					=> $_SERVER['SCRIPT_NAME']
		);

		$table_data						= template( $table_template_entity, $table_template_contents );
		
		return $table_data;
	}
	
	function edit() {
		global $TPL, $status;

		$script_url						= $_SERVER['SCRIPT_NAME'];

		$model_id						= $_REQUEST['id'];
		$model_name						= '';

		$edit_template_name					= $this->prefix . '_edit';
		$edit_template_entity					= $TPL[$edit_template_name];

		$brand_selection_template_name				= $this->prefix . '_brand_selection';
		$brand_selection_template_entity			= $TPL[$brand_selection_template_name];

		$brand_selection_row_template_name			= $this->prefix . '_brand_selection_row';
		$brand_selection_row_template_entity			= $TPL[$brand_selection_row_template_name];

		$edit_action						= $this->prefix . '-update';

		$select_model_query = "
			SELECT
				name, brand_id
			FROM
				models
			WHERE
				id = $model_id
		";

		$select_model_data					= db_get( $select_model_query );
		
		if ( $select_model_data ) {
			$model_name					= $select_model_data[0]['name'];
			$model_brand_id					= $select_model_data[0]['brand_id'];
		}

		$select_brands_query = "
			SELECT
				id, name
			FROM
				brands
		";

		$select_brands_data					= db_get( $select_brands_query );

		$brand_selection_rows					= '';

		if ( $select_brands_data ) {
			foreach ( $select_brands_data as $select_brands_data_row ) {
				$brand_id				= $select_brands_data_row['id'];
				$brand_name				= $select_brands_data_row['name'];
				$brand_selected				= ( $brand_id == $model_brand_id ) ? 'selected' : '';

				$brand_selection_row_template_contents = array(
					'id'				=> $brand_id,
					'name'				=> $brand_name,
					'selected'			=> $brand_selected
				);

				$brand_selection_row			= template( $brand_selection_row_template_entity,
									    $brand_selection_row_template_contents );

				$brand_selection_rows			.= $brand_selection_row;
			}
		}
		
		$brand_selection_template_contents = array(
			'rows'						=> $brand_selection_rows
		);
		
		$brand_selection					= template( $brand_selection_template_entity,
									    $brand_selection_template_contents );

		$edit_template_contents	= array(
			'script'					=> $script_url,
			'id'						=> $model_id,
			'do'						=> $edit_action,
			'name'						=> $model_name,
			'brand_selection'				=> $brand_selection
		);

		$edit_data						= template( $edit_template_entity, $edit_template_contents );

		return $edit_data;
	}
	
	function update() {
		global $PG;

		$model_id						= $_REQUEST['id'];
		$model_name						= $_REQUEST['name'];
		$model_brand_id						= $_REQUEST['brand_id'];

		$update_models_query = "
			UPDATE
				models
			SET
				name = '$model_name',
				brand_id = $model_brand_id
			WHERE
				id = $model_id
		";
		
		$update_models_data					= db( $update_models_query );

		$update_result						= $PG[pg_result_status( $update_models_data )];

		return $update_result;
	}
}

?>
