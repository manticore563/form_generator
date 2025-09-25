<?php
/**
 * FormManager Class
 * Handles CRUD operations for forms and form fields
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

class FormManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new form
     * @param string $title
     * @param string $description
     * @return string|false Form ID on success, false on failure
     */
    public function createForm($title, $description = '') {
        try {
            $formId = $this->generateUniqueId();
            $shareLink = $this->generateShareLink();
            
            $query = "INSERT INTO forms (id, title, description, config, share_link, created_at, updated_at, is_active) 
                     VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1)";
            
            $defaultConfig = json_encode([
                'fields' => [],
                'settings' => [
                    'submit_button_text' => 'Submit',
                    'success_message' => 'Thank you for your submission!',
                    'allow_multiple_submissions' => false
                ]
            ]);
            
            $result = $this->db->query($query, [$formId, $title, $description, $defaultConfig, $shareLink]);
            
            if ($result) {
                return $formId;
            }
            return false;
        } catch (Exception $e) {
            error_log("FormManager::createForm - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get form configuration by ID
     * @param string $formId
     * @return array|false
     */
    public function getFormConfig($formId) {
        try {
            $query = "SELECT * FROM forms WHERE id = ?";
            $stmt = $this->db->query($query, [$formId]);
            
            if ($stmt && $row = $stmt->fetch()) {
                $row['config'] = json_decode($row['config'], true);
                return $row;
            }
            return false;
        } catch (Exception $e) {
            error_log("FormManager::getFormConfig - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update form configuration
     * @param string $formId
     * @param array $config
     * @return bool
     */
    public function updateFormConfig($formId, $config) {
        try {
            $query = "UPDATE forms SET config = ?, updated_at = NOW() WHERE id = ?";
            $result = $this->db->query($query, [json_encode($config), $formId]);
            return $result !== false;
        } catch (Exception $e) {
            error_log("FormManager::updateFormConfig - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update form basic information
     * @param string $formId
     * @param string $title
     * @param string $description
     * @return bool
     */
    public function updateForm($formId, $title, $description = '') {
        try {
            $query = "UPDATE forms SET title = ?, description = ?, updated_at = NOW() WHERE id = ?";
            $result = $this->db->query($query, [$title, $description, $formId]);
            return $result !== false;
        } catch (Exception $e) {
            error_log("FormManager::updateForm - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a form and all associated data
     * @param string $formId
     * @return bool
     */
    public function deleteForm($formId) {
        try {
            $this->db->beginTransaction();
            
            // Delete form (cascade will handle related records)
            $query = "DELETE FROM forms WHERE id = ?";
            $result = $this->db->query($query, [$formId]);
            
            if ($result) {
                $this->db->commit();
                return true;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("FormManager::deleteForm - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all forms for admin dashboard
     * @return array
     */
    public function getAllForms() {
        try {
            $query = "SELECT id, title, description, share_link, created_at, updated_at, is_active,
                     (SELECT COUNT(*) FROM submissions WHERE form_id = forms.id) as submission_count
                     FROM forms ORDER BY created_at DESC";
            
            $stmt = $this->db->query($query);
            
            if ($stmt) {
                return $stmt->fetchAll();
            }
            return [];
        } catch (Exception $e) {
            error_log("FormManager::getAllForms - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate unique share link for form
     * @return string
     */
    public function generateShareLink() {
        do {
            $shareLink = $this->generateRandomString(12);
            $query = "SELECT id FROM forms WHERE share_link = ?";
            $stmt = $this->db->query($query, [$shareLink]);
        } while ($stmt && $stmt->fetch());
        
        return $shareLink;
    }
    
    /**
     * Activate or deactivate a form
     * @param string $formId
     * @param bool $isActive
     * @return bool
     */
    public function setFormStatus($formId, $isActive) {
        try {
            $query = "UPDATE forms SET is_active = ?, updated_at = NOW() WHERE id = ?";
            $result = $this->db->query($query, [$isActive ? 1 : 0, $formId]);
            return $result !== false;
        } catch (Exception $e) {
            error_log("FormManager::setFormStatus - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get form by share link
     * @param string $shareLink
     * @return array|false
     */
    public function getFormByShareLink($shareLink) {
        try {
            $query = "SELECT * FROM forms WHERE share_link = ? AND is_active = 1";
            $stmt = $this->db->query($query, [$shareLink]);
            
            if ($stmt && $row = $stmt->fetch()) {
                $row['config'] = json_decode($row['config'], true);
                return $row;
            }
            return false;
        } catch (Exception $e) {
            error_log("FormManager::getFormByShareLink - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add field to form configuration
     * @param string $formId
     * @param array $fieldConfig
     * @return bool
     */
    public function addField($formId, $fieldConfig) {
        try {
            $form = $this->getFormConfig($formId);
            if (!$form) {
                return false;
            }
            
            $config = $form['config'];
            $fieldConfig['id'] = $this->generateUniqueId();
            $config['fields'][] = $fieldConfig;
            
            return $this->updateFormConfig($formId, $config);
        } catch (Exception $e) {
            error_log("FormManager::addField - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update field in form configuration
     * @param string $formId
     * @param string $fieldId
     * @param array $fieldConfig
     * @return bool
     */
    public function updateField($formId, $fieldId, $fieldConfig) {
        try {
            $form = $this->getFormConfig($formId);
            if (!$form) {
                return false;
            }
            
            $config = $form['config'];
            foreach ($config['fields'] as &$field) {
                if ($field['id'] === $fieldId) {
                    $field = array_merge($field, $fieldConfig);
                    break;
                }
            }
            
            return $this->updateFormConfig($formId, $config);
        } catch (Exception $e) {
            error_log("FormManager::updateField - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove field from form configuration
     * @param string $formId
     * @param string $fieldId
     * @return bool
     */
    public function removeField($formId, $fieldId) {
        try {
            $form = $this->getFormConfig($formId);
            if (!$form) {
                return false;
            }
            
            $config = $form['config'];
            $config['fields'] = array_filter($config['fields'], function($field) use ($fieldId) {
                return $field['id'] !== $fieldId;
            });
            
            // Re-index array
            $config['fields'] = array_values($config['fields']);
            
            return $this->updateFormConfig($formId, $config);
        } catch (Exception $e) {
            error_log("FormManager::removeField - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate unique ID
     * @return string
     */
    private function generateUniqueId() {
        return uniqid('', true);
    }
    
    /**
     * Generate random string
     * @param int $length
     * @return string
     */
    private function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
    /**
     * Get full share URL for a form
     * @param string $shareLink
     * @return string
     */
    public function getShareUrl($shareLink) {
        return APP_URL . '/forms/view.php?link=' . $shareLink;
    }
    
    /**
     * Validate share link format
     * @param string $shareLink
     * @return bool
     */
    public function isValidShareLink($shareLink) {
        return preg_match('/^[a-zA-Z0-9]{12}$/', $shareLink);
    }
    
    /**
     * Get form statistics
     * @param string $formId
     * @return array
     */
    public function getFormStats($formId) {
        try {
            $query = "SELECT 
                        COUNT(*) as total_submissions,
                        COUNT(CASE WHEN DATE(submitted_at) = CURDATE() THEN 1 END) as today_submissions,
                        COUNT(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_submissions,
                        MIN(submitted_at) as first_submission,
                        MAX(submitted_at) as last_submission
                      FROM submissions 
                      WHERE form_id = ?";
            
            $stmt = $this->db->query($query, [$formId]);
            
            if ($stmt && $row = $stmt->fetch()) {
                return $row;
            }
            return [
                'total_submissions' => 0,
                'today_submissions' => 0,
                'week_submissions' => 0,
                'first_submission' => null,
                'last_submission' => null
            ];
        } catch (Exception $e) {
            error_log("FormManager::getFormStats - " . $e->getMessage());
            return [];
        }
    }
}
?>