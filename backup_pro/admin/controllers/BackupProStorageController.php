<?php

use mithra62\BackupPro\Platforms\Controllers\Wordpress AS WpController;
use mithra62\BackupPro\BackupPro AS BpInterface;

class BackupProStorageController extends WpController implements BpInterface
{   
    /**
     * The default Storage form field values
     * @var unknown
     */
    public $storage_form_data_defaults = array(
        'storage_location_name' => '',
        'storage_location_file_use' => '1',
        'storage_location_status' => '1',
        'storage_location_db_use' => '1',
        'storage_location_include_prune' => '1',
    );
        
    /**
     * View all the storage entries 
     * @return string
     */
    public function view_storage()
    {
        $variables = array();
        $variables['can_remove'] = true;
        if( count($this->settings['storage_details']) <= 1 )
        {
            $variables['can_remove'] = false;
        }
    
        $variables['errors'] = $this->errors;
        $variables['available_storage_engines'] = $this->services['backup']->getStorage()->getAvailableStorageDrivers();
        $variables['storage_details'] = $this->settings['storage_details'];
        $variables['menu_data'] = $this->backup_lib->getSettingsViewMenu();
        $variables['section'] = 'storage';
        $variables['view_helper'] = $this->view_helper;
        $variables['url_base'] = $this->url_base;
        $variables['theme_folder_url'] = plugin_dir_url(self::name);
        $template = 'admin/views/storage';
        $this->renderTemplate($template, $variables);
    }
    
    /**
     * Add a storage entry
     * @return string
     */
    public function new_storage()
    {
        $engine = $this->getPost('engine', 'local');
        $variables = array();
        $variables['available_storage_engines'] = $this->services['backup']->getStorage()->getAvailableStorageDrivers();
    
        if( !isset($variables['available_storage_engines'][$engine]) )
        {
            $engine = 'local';
        }
        
        $variables['storage_details'] = $this->settings['storage_details'];    
        $variables['storage_engine'] = $variables['available_storage_engines'][$engine];
        $variables['form_data'] = array_merge($this->settings, $variables['storage_engine']['settings'], $this->storage_form_data_defaults);
        $variables['form_errors'] = array_merge($this->returnEmpty($this->settings), $this->returnEmpty($variables['storage_engine']['settings']), $this->storage_form_data_defaults);
    
        if( $_SERVER['REQUEST_METHOD'] == 'POST' )
        {
            $data = array();
            $data = array_map( 'stripslashes_deep', $_POST );
            
            $variables['form_data'] = $data;
            $settings_errors = $this->services['backup']->getStorage()->validateDriver($this->services['validate'], $engine, $data, $this->settings['storage_details']);
            if( !$settings_errors )
            {
                if( $this->services['backup']->getStorage()->getLocations()->setSetting($this->services['settings'])->create($engine, $variables['form_data']) )
                {
                    ee()->session->set_flashdata('message_success', $this->services['lang']->__('storage_location_added'));
                    ee()->functions->redirect($this->url_base.'view_storage');
                }
            }
            else
            {
                $variables['form_errors'] = array_merge($variables['form_errors'], $settings_errors);
            }
        }
    
        $variables['errors'] = $this->errors;
        $variables['_form_template'] = false;
        if( $variables['storage_engine']['obj']->hasSettingsView() )
        {
            $variables['_form_template'] = 'drivers/_'.$engine.'.php';
        }

        $variables['menu_data'] = $this->backup_lib->getSettingsViewMenu();
        $variables['section'] = 'storage';
        $variables['engine'] = $engine;
        $variables['view_helper'] = $this->view_helper;
        $variables['url_base'] = $this->url_base;
        $variables['theme_folder_url'] = plugin_dir_url(self::name);
        
        //ee()->view->cp_page_title = $this->services['lang']->__('storage_bp_settings_menu');
        //return ee()->load->view('storage/new', $variables, true);
        $template = 'admin/views/storage/new';
        $this->renderTemplate($template, $variables);
    }
    
    /**
     * Edit a storage entry
     * @return string
     */    
    public function edit_storage()
    {
        $storage_id = ee()->input->get_post('id');
        if( empty($this->settings['storage_details'][$storage_id]) )
        {
            ee()->session->set_flashdata('message_error', $this->services['lang']->__('invalid_storage_id'));
            ee()->functions->redirect($this->url_base.'view_storage');
        }
    
        $storage_details = $this->settings['storage_details'][$storage_id];
    
        $variables = array();
        $variables['storage_details'] = $storage_details;
        $variables['form_data'] = array_merge($this->storage_form_data_defaults, $storage_details);
        $variables['form_errors'] = $this->returnEmpty($storage_details); //array_merge($storage_details, $this->form_data_defaults);
        $variables['errors'] = $this->errors;
        $variables['available_storage_engines'] = $this->services['backup']->getStorage()->getAvailableStorageOptions();
        $variables['storage_engine'] = $variables['available_storage_engines'][$storage_details['storage_location_driver']];
        $variables['_form_template'] = 'storage/drivers/_'.$storage_details['storage_location_driver'];
    
        if( ee()->input->server('REQUEST_METHOD') == 'POST' )
        {
            $data = array();
            foreach($_POST as $key => $value){
                $data[$key] = ee()->input->post($key);
            }
            
            $variables['form_data'] = $data;
            $settings_errors = $this->services['backup']->getStorage()->validateDriver($this->services['validate'], $storage_details['storage_location_driver'], $data, $this->settings['storage_details']);
            if( !$settings_errors )
            {
                if( $this->services['backup']->getStorage()->getLocations()->setSetting($this->services['settings'])->update($storage_id, $variables['form_data']) )
                {
                    ee()->session->set_flashdata('message_success', $this->services['lang']->__('storage_location_updated'));
                    ee()->functions->redirect($this->url_base.'view_storage');
                }
            }
            else
            {
                $variables['form_errors'] = array_merge($variables['form_errors'], $settings_errors);
            }
        }

        $variables['menu_data'] = ee()->backup_pro->get_settings_view_menu();
        $variables['section'] = 'storage';
        $variables['storage_id'] = $storage_id;
        ee()->view->cp_page_title = $this->services['lang']->__('storage_bp_settings_menu');
        return ee()->load->view('storage/edit', $variables, true);
    }
    
    /**
     * Remove a storage entry
     * @return string
     */    
    public function remove_storage()
    {
        if( count($this->settings['storage_details']) <= 1 )
        {
            ee()->session->set_flashdata('message_error', $this->services['lang']->__('min_storage_location_needs'));
            ee()->functions->redirect($this->url_base.'view_storage');
        }
    
        $storage_id = ee()->input->get_post('id');
        if( empty($this->settings['storage_details'][$storage_id]) )
        {
            ee()->session->set_flashdata('message_error', $this->services['lang']->__('invalid_storage_id'));
            ee()->functions->redirect($this->url_base.'view_storage');
        }
    
        $storage_details = $this->settings['storage_details'][$storage_id];
    
        $variables = array();
        $variables['form_data'] = array('remove_remote_files' => '0');
        $variables['form_errors'] = array('remove_remote_files' => false);
        $variables['errors'] = $this->errors;
        $variables['available_storage_engines'] = $this->services['backup']->getStorage()->getAvailableStorageDrivers();
        $variables['storage_engine'] = $variables['available_storage_engines'][$storage_details['storage_location_driver']];
        $variables['storage_details'] = $storage_details;
    
        if( ee()->input->server('REQUEST_METHOD') == 'POST' )
        {
            $data = array();
            foreach($_POST as $key => $value){
                $data[$key] = ee()->input->post($key);
            }
            
            $backups = $this->services['backups']->setBackupPath($this->settings['working_directory'])
            ->getAllBackups($this->settings['storage_details'], $this->services['backup']->getStorage()->getAvailableStorageDrivers());
    
            if( $this->services['backup']->getStorage()->getLocations()->setSetting($this->services['settings'])->remove($storage_id, $data, $backups) )
            {
                ee()->session->set_flashdata('message_success', $this->services['lang']->__('storage_location_removed'));
                ee()->functions->redirect($this->url_base.'view_storage');
            }
            else
            {
                $variables['form_errors'] = array_merge($variables['form_errors'], $settings_errors);
            }
        }

        $variables['menu_data'] = ee()->backup_pro->get_settings_view_menu();
        $variables['section'] = 'storage';
        $variables['storage_id'] = $storage_id;
        ee()->view->cp_page_title = $this->services['lang']->__('storage_bp_settings_menu');
        return ee()->load->view('storage/remove', $variables, true);
    }
    
}