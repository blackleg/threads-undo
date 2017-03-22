<?php

elgg_register_event_handler('init', 'system', 'undo_threads_init');

elgg_register_event_handler('upgrade', 'system', 'undo_threads_launch');

function undo_threads_init() {
    
}

/**
 * Launch Som Energia upgrades 
 */
function undo_threads_launch() {
    if (!elgg_is_active_plugin('threads')) {
        upgrade_old_threads_topicreply();
    }
}

function upgrade_old_threads_topicreply() {
    $replies = elgg_get_entities(array('type' => 'object', 'subtype' => 'topicreply', 'limit' => 0));
    if (count($replies) > 0) {
        error_log('=> Undo old lorea threads topiceply: ' . count($replies) . ' replies');
        foreach ($replies as $reply) {
            remove_old_thread_reply_title($reply);
            $topic = get_reply_topic($reply);
            change_reply_container($reply, $topic);
            change_reply_access_id($reply, $topic);
            force_object_remove_relation($reply->guid, 'parent');
            force_object_remove_relation($reply->guid, 'top');
        }
        if (!force_object_subtype_change('topicreply', 'discussion_reply')) {
            error_log("Fail upgrading old threads topiceply");
        }
    }
}

function get_reply_topic($reply) {
    $topRelations = elgg_get_entities_from_relationship(array("relationship" => "top", "relationship_guid" => $reply->guid, 'limit' => 0));
    foreach ($topRelations as $top) {
        if ($top->getSubtype() == 'groupforumtopic') {
            return $top;
        }
    }
    return NULL;
}

function change_reply_access_id($reply, $topic) {
    if (!is_null($topic) && $reply->access_id != $topic->access_id) {
        force_object_access_change($reply->guid, $topic->access_id);
    }
}

function change_reply_container($reply, $topic) {
    if (!is_null($topic) && $reply->container_guid != $topic->guid) {
        force_object_container_change($reply->guid, $topic->guid);
    }
}

function force_object_remove_relation($object_guid_param, $relation_param) {
    $object_guid = sanitize_string($object_guid_param);
    $relation = sanitize_string($relation_param);
    $dbprefix = elgg_get_config('dbprefix');
    $query = "DELETE FROM {$dbprefix}entity_relationships WHERE guid_one='$object_guid' AND relationship='$relation'";
    if (!update_data($query)) {
        error_log('Fail remove object:  ' . $object_guid . ' relation: ' . $relation);
    }
}

function force_object_access_change($from_object_guid_param, $to_access_id_param) {
    $object_guid = sanitize_string($from_object_guid_param);
    $to_access_id = sanitize_string($to_access_id_param);
    $dbprefix = elgg_get_config('dbprefix');
    $query = "UPDATE {$dbprefix}entities SET access_id='$to_access_id' WHERE guid='$object_guid'";
    if (!update_data($query)) {
        error_log('Fail access change:  ' . $object_guid . ' to container: ' . $to_access_id);
    }
}

function force_object_container_change($from_object_guid_param, $to_container_guid_param) {
    $object_guid = sanitize_string($from_object_guid_param);
    $to_container_guid = sanitize_string($to_container_guid_param);
    $dbprefix = elgg_get_config('dbprefix');
    $query = "UPDATE {$dbprefix}entities SET container_guid='$to_container_guid' WHERE guid='$object_guid'";
    if (!update_data($query)) {
        error_log('Fail container guid change:  ' . $object_guid . ' to container: ' . $to_container_guid);
    }
}

function remove_old_thread_reply_title($reply) {
    if ($reply->title != NULL) {
        $reply->title = NULL;
        if (!$reply->save()) {
            error_log('Error on remove old reply title: ' . $reply->guid);
        }
    }
}

/**
 * Forcefully changes the subtype of all objects with a given subtype
 *
 * @param string $from_subtype_param Current subtype alias
 * @param string $to_subtype_param   Future subtype alias
 * @return boolean
 */
function force_object_subtype_change($from_subtype_param, $to_subtype_param) {
    $from_subtype = sanitize_string($from_subtype_param);
    $to_subtype = sanitize_string($to_subtype_param);

    $from_subtype_id = add_subtype('object', $from_subtype);
    $to_subtype_id = add_subtype('object', $to_subtype);

    $dbprefix = elgg_get_config('dbprefix');

    $query = "UPDATE {$dbprefix}entities SET subtype=$to_subtype_id WHERE subtype='$from_subtype_id'";
    if (update_data($query)) {
        $query_river = "UPDATE {$dbprefix}river SET subtype='$to_subtype'WHERE subtype='$from_subtype'";
        $result_river = update_data($query_river);
        if (!$result_river) {
            error_log("Fail updating river");
        }
        error_log("Update subtype and class references in system log table.");
        $to_class = get_subtype_class('object', $to_subtype);
        if (!$to_class) {
            $to_class = 'ElggObject';
        }

        $query_log = "UPDATE {$dbprefix}system_log SET object_subtype='$to_subtype',object_class='$to_class' WHERE object_subtype='$from_subtype'";
        $result_log = update_data($query_log);
        if (!$result_log) {
            error_log("Fail updating system log");
        }

        return ($result_river && $result_log);
    }

    return false;
}
