<?php

namespace Drupal\bestreply\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\comment\CommentInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Controller routines for bestreply routes.
 */
class BestReplyController extends ControllerBase {

  /**
   * Insert or update the marked comment info.
   */
  public function mark(CommentInterface $comment, $js = NULL) {
    $user = \Drupal::currentUser();
    $rt = FALSE;
    $dt = REQUEST_TIME;

    if ($comment->getCommentedEntityTypeId() == 'node') {
      if ($comment->isPublished()) {
        if (BestReplyController::bestreplyIsmarked($comment->getCommentedEntityId())) {
          $action = 'replace';
          $rt = db_query('UPDATE {bestreply} SET cid = :cid, aid = :aid, uid = :uid, dt = :dt  where nid = :nid',
              array(
                'cid' => $comment->id(),
                'aid' => $comment->getOwnerId(),
                'uid' => $user->id(),
                'dt' => $dt,
                'nid' => $comment->getCommentedEntityId(),
              ));
        }
        else {
          $action = 'mark';
          $rt = db_query('INSERT into {bestreply} values( :nid, :cid, :aid, :uid, :dt)',
              array(
                'nid' => $comment->getCommentedEntityId(),
                'cid' => $comment->id(),
                'aid' => $comment->getOwnerId(),
                'uid' => $user->id(),
                'dt' => $dt,
              ));
        }

        if ($js) {
          $status = ($rt) ? TRUE : FALSE;
          print Json::encode(array(
            'status' => $status,
            'cid' => $comment->id(),
            'action' => $action,
          ));
          exit;
        }
      }
    }
  }

  /**
   * Return the marked cid (comment id) for the given node id.
   */
  public static function bestreplyIsmarked($nid = NULL) {
    if (!$nid) {
      return FALSE;
    }
    return db_query('SELECT cid FROM {bestreply} WHERE nid = :nid', array('nid' => $nid))->fetchField();
  }

  /**
   * Clear the marked comment info.
   */
  public function clear(CommentInterface $comment, $js = NULL) {
    if (BestReplyController::bestreplyIsmarked($comment->getCommentedEntityId())) {
      $rt = db_query("DELETE FROM {bestreply} WHERE nid = :nid", array('nid' => $comment->getCommentedEntityId()));
    }
    if ($js) {
      $status = ($rt) ? TRUE : FALSE;
      print Json::encode(array(
        'status' => $status,
        'cid' => $comment->id(),
        'action' => 'clear',
      ));
      exit;
    }
  }

  /**
   * List all the best reply data.
   */
  public function replyCommentList() {
    $head = array(
    array('data' => 'title'),
    array('data' => 'author', 'field' => 'cname', 'sort' => 'asc'),
    array('data' => 'marked by', 'field' => 'name', 'sort' => 'asc'),
    array('data' => 'when', 'field' => 'dt', 'sort' => 'asc'),
    );

    $sql = db_select('bestreply', 'b')
    ->fields('b', array('nid', 'cid', 'uid', 'aid', 'dt'));

    $sql->join('node_field_data', 'n', 'n.nid = b.nid' );
    $sql->addField('n', 'title');
    $sql->join('comment_field_data', 'c', 'c.cid = b.cid');
    $sql->addField('c', 'name', 'cname');
    $sql->join('users_field_data', 'u', 'u.uid = b.uid');
    $sql->addField('u', 'name');

    $sql = $sql->extend('Drupal\Core\Database\Query\PagerSelectExtender')->extend('Drupal\Core\Database\Query\TableSortExtender')->orderByHeader($head);
    $result = $sql->execute()->fetchAll();

    foreach ($result as $reply) {
      $options = array('fragment' => 'comment-' . $reply->cid);
      $author = !empty($reply->aid) ? Link::fromTextAndUrl($reply->cname, Url::fromUri('entity:user/' . $reply->aid)) : \Drupal::config('user.settings')->get('anonymous');
      $reply_user = !empty($reply->uid) ? Link::fromTextAndUrl($reply->name, Url::fromUri('entity:user/' . $reply->uid)) : \Drupal::config('user.settings')->get('anonymous'); 
      $rows[] = array(
        Link::fromTextAndUrl($reply->title, Url::fromUri('entity:node/' . $reply->nid,$options)),
        $author,
        $reply_user,
        $this->t('!time ago', array('!time' => \Drupal::service('date.formatter')->formatInterval(REQUEST_TIME - $reply->dt)))
      );
    }

    if (isset($rows)) {
      // Add the pager.
      $build['content'] = array(
        '#theme' => 'table',
        '#header' => $head,
        '#rows' => $rows,
      );
      $build['pager'] = array(
        '#theme' => 'pager',
        '#weight' => 5,
      );
      return $build;
    }
    else {
      return array(
        '#markup' => t('No results to display'),
      );
    }
  }

}
