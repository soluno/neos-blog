<?php

namespace ObisConcept\NeosBlog\Controller;

    /*
     * This file is part of the ObisConcept.NeosBlog package.
     *
     * (c) Dennis Schröder
     *
     * This package is Open Source Software. For the full copyright and license
     * information, please view the LICENSE file which was distributed with this
     * source code.
     */


use Neos\Flow\Annotations as Flow;
use ObisConcept\NeosBlog\Domain\Model\Category;
use ObisConcept\NeosBlog\Domain\Service\BlogService;
use ObisConcept\NeosBlog\Domain\Service\ContentService;
use ObisConcept\NeosBlog\Domain\Service\PostService;
use Neos\Neos\Controller\Module\ManagementController;
use Neos\Neos\Domain\Service\ContentContextFactory;
use Neos\Neos\Domain\Service\UserService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeTemplate;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\ContentDimensionRepository;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;


/**
 * Class BlogController
 * @package ObisConcept\NeosBlog\Controller
 * @Flow\Scope("singleton")
 */

class ArchiveController extends ManagementController {


    /**
     * @Flow\Inject
     * @var BlogService
     */

    protected $blogService;


    /**
     * @Flow\Inject
     * @var PostService
     */

    protected $postService;

    /**
     * @Flow\Inject
     * @var ContentService
     */

    protected $contentService;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

  /**
   * @Flow\Inject
   * @var ContentContextFactory
   */
  protected $contentContextFactory;

  /**
   * @Flow\Inject
   * @var UserService
   */
  protected $userService;


  /**
   * @Flow\Inject
   * @var ContentDimensionRepository
   */
  protected $contentDimensionsRepository;


  /**
   * Shows a list of post nodes which are accessible by the current user
   * based on the personal workspaces and the default dimension
   *
   * @param NodeInterface $blogNode
   * @param Workspace $workspaceObject
   * @param array $authorName
   * @param Category $categoryObject
   * @param array $dimension
   * @param array $dimensionLabel
   *
   * ToDo: Refactor indexAction / Move some of the code into separate Service classes and create own queries to get the data form the database
   */


  public function indexAction(NodeInterface $blogNode = null, Workspace $workspaceObject = null, array $authorName = null, Category $categoryObject = null, $dimension = array(), $dimensionLabel = array()) {


    if (!$workspaceObject == null) {
      $personalPosts = $this->postService->getPostsFilteredByWorkspace($workspaceObject, $dimension);
    } elseif (!$authorName == null) {
      $personalPosts = $this->postService->getPersonalPosts($authorName[0], $dimension);
    } elseif (!$categoryObject == null) {
      $personalPosts = $this->postService->getPostsWithCategoryRelation($categoryObject, $dimension);
    } elseif (!$blogNode == null) {
      $personalPosts = $blogNode->getChildNodes('ObisConcept.NeosBlog:Post');
    } else {
      $personalPosts = $this->postService->getPersonalPosts(' ',$dimension);
    }

    // unset archived posts
    /** @var NodeInterface $personalPost */
    foreach ($personalPosts as $key => $personalPost) {

      if($personalPost->getProperty('archived') == false) {
        unset($personalPosts[$key]);
      }
    }

    $sortedPosts = array();

    /** @var NodeInterface $post */
    foreach($personalPosts as $post) {
      $sortedPosts[$post->getProperty('publishedAt')->format('d.m.Y H:i:s')] = $post;
    }

    if ($sortedPosts !== null) {
      usort($sortedPosts, function($postA, $postB) {
        return ($postA->getProperty('publishedAt') > $postB->getProperty('publishedAt')) ? -1 : 1;
      });
    }

    usort($sortedPosts, function($postA, $postB) {
      return ($postA->getProperty('publishedAt') > $postB->getProperty('publishedAt')) ? -1 : 1;
    });

    /** @var NodeInterface $a */
    $nodes = $sortedPosts;

    $workspacesInArray = array();
    $authorsInArray = array();
    $blogsInArray = array();
    $categoryInArray = array();

    /** @var NodeInterface $post */
    foreach ($personalPosts as $post) {
      $workspacesInArray[$post->getWorkspace()->getName()] = $post->getWorkspace();
      $authorsInArray[] = $post->getProperty('author');

      $blogName = ($post->getParent() !== null ? $post->getParent()->getProperty('title'): null);
      $blogNode = ($post->getParent() !== null ? $post->getParent() : null);
      if (!$blogName == null) $blogsInArray[$blogName] = $blogNode;

      $categoryName = ($post->getProperty('categories') !== null ? $post->getProperty('categories')->getName(): null);
      $category = ($post->getProperty('categories') !== null ? $post->getProperty('categories'): null);
      if (!$categoryName == null) $categoryInArray[$categoryName] = $category;
    }

    $workspaceFilterMenu = $workspacesInArray;
    $authorFilterMenu = array_unique($authorsInArray);
    $categoryFilterMenu = $categoryInArray;
    $blogFilterMenu = $blogsInArray;

    $this->view->assign('blogFilterMenu', $blogFilterMenu);
    $this->view->assign('workspaceFilterMenu', $workspaceFilterMenu);
    $this->view->assign('authorFilterMenu', $authorFilterMenu);
    $this->view->assign('categoryFilterMenu', $categoryFilterMenu);


    $blogNodes = $this->blogService->getPersonalBlogs();
    $this->view->assign('blogs', $blogNodes);

    $userWorkspace = $this->_userService->getPersonalWorkspaceName();
    $this->view->assign('personalWorkspace', $userWorkspace);

    if (!$nodes == null){
      $this->view->assign('posts', $nodes);
    }


    $languageDimensions = $this->postService->getLanguageDimensions();
    $defaultLanguage = $this->postService->getDefaultLanguage();

    if(empty($dimension) == false) {

      $this->view->assign('dimensionLabel', $dimensionLabel[0]);
      unset($languageDimensions[$dimensionLabel[0]]);

    } else {

      unset($languageDimensions[$defaultLanguage]);
      $this->view->assign('defaultLanguage', $defaultLanguage);

    }

    $this->view->assign('dimensions', $languageDimensions);

    $postCount = count($nodes);
    $this->view->assign('postCount', $postCount);
  }

  /**
   * Moves the archived Post back to normal post status
   * @param NodeInterface $postNode
   */
  public function moveAction(NodeInterface $postNode) {
      $postNode->setProperty('archived', false);

      $this->redirect('index');
    }

    /**
     * Shows the details of one post node
     * @param NodeInterface $post
     */
    
    public function showAction(NodeInterface $post) {

        if(!$post == Null) {
            $imageResource = $this->contentService->getPostImageResourceObject($post);
            $teaserText = $this->contentService->getPostTextTeaser($post);

            if (!$teaserText == null) {
                $this->view->assign('postTextTeaser', $teaserText);
                $this->view->assign('postImage', $imageResource[0]);
            }

            /** @var NodeInterface $personalPosts */
            $properties =  $post->getProperties();

            //make each property available in the template with it's property name
            foreach ($properties as $propertyName => $property) {
                $this->view->assign($propertyName, $property);
            }

            $this->view->assign('post', $post);
        } else {

        }
    }

    /**
     * Deletes the specified node and all of its sub nodes
     *
     * @param $postNode
     */
    public function deleteAction(NodeInterface $postNode) {

        if ($this->request->getHttpRequest()->isMethodSafe() === false) {
            $this->persistenceManager->persistAll();
        }

        /** @var NodeInterface $node */
        $postNode->remove();
        $this->redirect('index');
    }


    protected function getBlogNode(string $workspace, string $blogIdentifier){
        $context = $this->contentContextFactory->create(['workspaceName' => $workspace]);

        $blogNode = $context->getNodeByIdentifier($blogIdentifier);

        if (!($blogNode instanceof NodeInterface)) {
           return;
        }

        return $blogNode;
    }
}