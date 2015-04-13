<?php

/**
 * This software is intended for use with Oxwall Free Community Software http://www.oxwall.org/ and is
 * licensed under The BSD license.

 * ---
 * Copyright (c) 2011, Oxwall Foundation
 * All rights reserved.

 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the
 * following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice, this list of conditions and
 *  the following disclaimer.
 *
 *  - Redistributions in binary form must reproduce the above copyright notice, this list of conditions and
 *  the following disclaimer in the documentation and/or other materials provided with the distribution.
 *
 *  - Neither the name of the Oxwall Foundation nor the names of its contributors may be used to endorse or promote products
 *  derived from this software without specific prior written permission.

 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED
 * AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Forum text search class.
 *
 * @author Alex Ermashev <alexermashev@gmail.com>
 * @package ow.ow_plugins.forum.classes
 * @since 1.0
 */
class FORUM_CLASS_ForumTextSearch
{
    /**
     * @var FORUM_CLASS_ForumTextSearch
     */
    private static $classInstance;

    /**
     * Search entity topic
     */
    const SEARCH_ENTITY_TYPE_TOPIC = 'forum_topic';

    /**
     * Search entity post
     */
    const SEARCH_ENTITY_TYPE_POST = 'forum_post';

    /**
     * Class constructor
     */
    private function __construct()
    {}

    /**
     * Get forum service
     * 
     * @return FORUM_BOL_ForumService
     */
    private function getForumService()
    {
        return FORUM_BOL_ForumService::getInstance();
    }

    /**
     * Returns class instance
     *
     * @return FORUM_CLASS_ForumTextSearch
     */
    public static function getInstance()
    {
        if ( !isset(self::$classInstance) )
            self::$classInstance = new self();

        return self::$classInstance;
    }

    /**
     * Delete post
     * 
     * @param integer $postId
     * @return void
     */
    public function deletePost( $postId )
    {
        OW::getTextSearchManager()->deleteAllEntitiesByTags(array(
            'forum_post_id_' . $postId
        ));   
    }

    /**
     * Save or update post
     * 
     * @param FORUM_BOL_Post $postDto
     * @return void
     */
    public function saveOrUpdatePost( FORUM_BOL_Post $postDto )
    {
        // get topic, group and section info
        $topicInfo = $this->getForumService()->getTopicInfo($postDto->topicId);
        $groupInfo = $this->getForumService()->getGroupInfo($topicInfo['groupId']);
        $sectionInfo = $this->getForumService()->findSectionById($groupInfo->sectionId);

        // delete old posts by tags
        $this->deletePost($postDto->id);

        // add a new one
        $postTags = array(
            'forum_post', // global search
            'forum_post_user_id_' . $postDto->userId, // global search by a specific user
            'forum_post_group_id_' . $topicInfo['groupId'], // search into a specific forum
            'forum_post_group_id_' . $topicInfo['groupId'] . '_user_id_' . $postDto->userId, // search into a specific forum
            'forum_post_section_id_' . $groupInfo->sectionId, // search into a specific section,
            'forum_post_section_id_' . $groupInfo->sectionId . '_user_id_' . $postDto->userId, // search into a specific section and specific user
            'forum_post_topic_id_'   . $topicInfo['id'], // search into a specific topic
            'forum_post_id_' . $postDto->id
        );

        if ( !$groupInfo->isPrivate && !$sectionInfo->isHidden ) 
        {
            $postTags = array_merge($postTags, array(
                'forum_post_public' // visible everywhere
            ));
        }

        if ( $topicInfo['status'] == FORUM_BOL_ForumService::STATUS_APPROVED )
        {
            OW::getTextSearchManager()->
                    addEntity(self::SEARCH_ENTITY_TYPE_POST, $postDto->id, $postDto->text, $postDto->createStamp, $postTags);
        }
        else
        {
            OW::getTextSearchManager()->addEntity(self::SEARCH_ENTITY_TYPE_POST, 
                    $postDto->id, $postDto->text, $postDto->createStamp, $postTags, OW_TextSearchManager::ENTITY_STATUS_NOT_ACTIVE);
        }

        // duplicate this post as a part of topic
        $topicTags = array(
           'forum_topic', // global search
           'forum_topic_user_id_' . $postDto->userId, // global search by a specific user
           'forum_topic_group_id_' . $topicInfo['groupId'], // search into a specific forum
           'forum_topic_group_id_' . $topicInfo['groupId'] . '_user_id_' . $postDto->userId, // search into a specific forum and specific user              
           'forum_topic_section_id_' . $groupInfo->sectionId, // search into a specific section
           'forum_topic_section_id_' . $groupInfo->sectionId . '_user_id_' . $postDto->userId, // search into a specific section and specific user
           'forum_post_topic_id_'   . $topicInfo['id'], // needed for global delete
           'forum_post_id_' . $postDto->id
        );
        
        if ( !$groupInfo->isPrivate && !$sectionInfo->isHidden ) 
        {
            $topicTags = array_merge($topicTags, array(
                'forum_topic_public' // visible everywhere
            ));
        }

        if ( $topicInfo['status'] == FORUM_BOL_ForumService::STATUS_APPROVED )
        {
            OW::getTextSearchManager()->addEntity(self::SEARCH_ENTITY_TYPE_TOPIC, 
                    $topicInfo['id'], $postDto->text, $postDto->createStamp, $topicTags);
        }
        else
        {
            OW::getTextSearchManager()->addEntity(self::SEARCH_ENTITY_TYPE_TOPIC, 
                    $topicInfo['id'], $postDto->text, $postDto->createStamp, $topicTags, OW_TextSearchManager::ENTITY_STATUS_NOT_ACTIVE);
        }
    }

    /**
     * Set topic status
     * 
     * @param integer $topicId
     * @return void
     */
    public function setTopicStatus( $topicId, $isActive = true )
    {
        $status = $isActive 
            ? OW_TextSearchManager::ENTITY_STATUS_ACTIVE 
            : OW_TextSearchManager::ENTITY_STATUS_NOT_ACTIVE;

        OW::getTextSearchManager()->setEntitiesStatusByTags(array(
            'forum_topic_id_' . $topicId,
            'forum_post_topic_id_' . $topicId
        ), $status);
    }

    /**
     * Activate entities
     * 
     * @return void
     */
    public function activateEntities()
    {
        OW::getTextSearchManager()->activateAllEntitiesByTags(array(
            'forum_topic',
            'forum_post'
        ));
    }

    /**
     * Deactivate entities
     * 
     * @return void
     */
    public function deactivateEntities()
    {
        OW::getTextSearchManager()->deactivateAllEntitiesByTags(array(
            'forum_topic',
            'forum_post'
        ));
    }

    /**
     * Delete all entities
     * 
     * @return void
     */
    public function deleteAllEntities()
    {
        OW::getTextSearchManager()->deleteAllEntitiesByTags(array(
            'forum_topic',
            'forum_post'
        )); 
    }

    /**
     * Add topic
     * 
     * @param FORUM_BOL_Topic $topicDto
     * @return void
     */
    public function addTopic( FORUM_BOL_Topic $topicDto )
    {
        $groupInfo = $this->getForumService()->getGroupInfo($topicDto->groupId);      
        $sectionInfo = $this->getForumService()->findSectionById($groupInfo->sectionId);

        $topicTags = array(
            'forum_topic', // global search
            'forum_topic_user_id_' . $topicDto->userId, // global search by a specific user
            'forum_topic_group_id_' . $topicDto->groupId, // search into a specific forum
            'forum_topic_group_id_' . $topicDto->groupId . '_user_id_' . $topicDto->userId, // search into a specific forum and specific user              
            'forum_topic_section_id_' . $groupInfo->sectionId, // search into a specific section
            'forum_topic_section_id_' . $groupInfo->sectionId . '_user_id_' . $topicDto->userId, // search into a specific section and specific user
            'forum_topic_id_' . $topicDto->id
        );

        if ( !$groupInfo->isPrivate && !$sectionInfo->isHidden ) 
        {
            $topicTags = array_merge($topicTags, array(
                'forum_topic_public' // visible everywhere
            ));
        }

        OW::getTextSearchManager()->
                addEntity(self::SEARCH_ENTITY_TYPE_TOPIC, $topicDto->id, $topicDto->title, time(), $topicTags);
    }

    /**
     * Save or update topic
     * 
     * @param FORUM_BOL_Topic $topicDto
     * @return void
     */
    public function saveOrUpdateTopic( FORUM_BOL_Topic $topicDto )
    {
        // delete old topic
        OW::getTextSearchManager()->deleteAllEntitiesByTags(array(
            'forum_topic_id_' . $topicDto->id
        ));

        $this->addTopic($topicDto);
    }

    /**
     * Delete topic 
     * 
     * @param integer $topicId
     * @return void
     */
    public function deleteTopic( $topicId )
    {
        OW::getTextSearchManager()->deleteAllEntitiesByTags(array(
            'forum_topic_id_' . $topicId, // delete the topic
            'forum_post_topic_id_' . $topicId, // delete all posts inside
        ));
    }
}
