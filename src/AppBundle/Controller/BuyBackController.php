<?php
namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
//use Symfony\Component\Console\Input\ArrayInput;
//use Symfony\Component\Console\Output\NullOutput;
//use Symfony\Component\Validator\Constraints\Time;
//use Symfony\Bundle\FrameworkBundle\Console\Application;
//use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;

use AppBundle\Form\BuyBackForm;
use AppBundle\Form\BuyBackHiddenForm;
use AppBundle\Model\BuyBackModel;
//use AppBundle\Model\BuyBackItemModel;
use AppBundle\Entity\LineItemEntity;
use AppBundle\Entity\TransactionEntity;
use AppBundle\Helper\MarketHelper;

class BuyBackController extends Controller
{


    /**
     * @Route("/buyback/estimate", name="ajax_estimate_buyback")
     */
    public function ajax_EstimateAction(Request $request) {

        //setup model and form
        $buyback = new BuyBackModel();
        $form = $this->createForm(BuyBackForm::class, $buyback);
        $form->handleRequest($request);

        //$types = $this->getDoctrine()->getRepository('EveBundle:TypeEntity', 'evedata');
        //$cache = $this->getDoctrine()->getRepository('AppBundle:CacheEntity', 'default');
        //$typeids = array();
        //$items = array();

        //parse form input
        $items = $this->get('parser')->GetLineItemsFromPasteData($buyback->getItems());

        //check to make sure it parsed correctly
        if($items == null || count($items) <= 0) {
            return $this->render('buyback/novalid.html.twig');
        }

        //get market value and buyback values
        if(!$this->get('market')->PopulateLineItems($items))
        {
            return $this->render('elements/error_modal.html.twig', Array( 'message' => "No Prices Found"));
        }

        //insert into DB and return quote

        //get DB manager
        $em = $this->getDoctrine()->getManager('default');

        //build transaction
        $transaction = new TransactionEntity();
        $transaction->setUser($this->getUser());
        $transaction->setType("P"); //will reset to PS if accepted with shares

        $transaction->setIsComplete(false);
        $transaction->setOrderId($transaction->getType() . uniqid());
        $transaction->setGross(0);
        $transaction->setNet(0);
        $transaction->setCreated(new \DateTime("now"));
        $transaction->setStatus("Estimate");
        $em->persist($transaction);


        //add line items to transaction
        $hasInvalid = false;

        /* @var $lineItem LineItemEntity */
        foreach($items as $lineItem)
        {
            if($lineItem->getIsValid()) {
                $em->persist($lineItem);
                $transaction->addLineitem($lineItem);
            } else {
                $hasInvalid = true;
            }
        }

        $em->flush();




        return $this->render('buyback/results.html.twig', Array ( 'items' => $items, 'total' => $transaction->getNet(),
            'hasInvalid' => $hasInvalid, 'orderId' => $transaction->getOrderId() ));
    }

    /**
     * @Route("/buyback/accept", name="ajax_accept_buyback")
     */
    public function ajax_AcceptAction(Request $request) {

        // Get our list of Items
        $total = $request->request->get('total');
        $order_id = $request->request->get('orderId');
        $shares = $request->request->get('shares');

        // Pull data from DB
        $em = $this->getDoctrine()->getManager('default');
        $transaction = $em->getRepository('AppBundle:TransactionEntity')->findOneByOrderId($order_id);

        //update status
        $transaction->setStatus("Pending");

        //update shares if needed
        if ($shares == 1) {
            $transaction->setType("PS");
        }

        $em->flush();

        $share_value = 0;

        return $this->render('buyback/accepted.html.twig', Array('auth_code' => $order_id, 'total_value' => $transaction->getNet(),
            'transaction' => $transaction, 'shares' => $shares, 'share_value' => $share_value));
    }

    /**
     * @Route("/buyback/decline", name="ajax_decline_buyback")
     */
    public function ajax_DeclineAction(Request $request) {

        // Get our list of Items
        $order_id = $request->request->get('orderId');

        // Pull data from DB
        $em = $this->getDoctrine()->getManager('default');
        $transaction = $em->getRepository('AppBundle:TransactionEntity')->findOneByOrderId($order_id);

        //delete transaction
        $em->remove($transaction);

        $em->flush();

        return new Response();
    }

    /**
     * @Route("/market/lookup", name="ajax_lookup_price")
     */
    public function ajax_LookupAction(Request $request) {

        if(is_numeric($request->request->get('id'))) {

            $typeId = $request->request->get('id');

            // Get Settings
            $bb_source_type = $this->get('helper')->getSetting("buyback_source_type");
            $bb_source_stat = $this->get('helper')->getSetting("buyback_source_stat");
            $bb_source_id =  $this->get('helper')->getSetting("buyback_source_id");

            $amarrData = $this->get('market')->GetEveCentralData($typeId, $bb_source_id);
            $jitaData = $this->get('market')->GetEveCentralData($typeId, "30000142");
            $dodixieData = $this->get('market')->GetEveCentralData($typeId, "30002659");
            $rensData = $this->get('market')->GetEveCentralData($typeId, "30002510");
            $hekData = $this->get('market')->GetEveCentralData($typeId, "30002053");
            $type = $this->getDoctrine()->getRepository('EveBundle:TypeEntity', 'evedata')->findOneByTypeID($typeId);
            $market_group = $this->getDoctrine()->getRepository('EveBundle:MarketGroupsEntity','evedata')
                ->findOneByMarketGroupID($type->getMarketGroupId())->getMarketGroupName();
            $group = $this->getDoctrine()->getRepository('EveBundle:GroupsEntity', 'evedata')
                ->findOneByGroupID($type->getGroupID())->getGroupName();
            $priceDetails = array();
            $priceDetails['types'] = array();
            $options = $this->get('market')->ProcessBuybackRules($typeId);
            $value = $this->get('market')->GetMarketPriceByComposition($type, $options, $priceDetails);
            $isPricedByMinerals = $this->get('market')->IsPricedByMinerals($typeId);

            $template = $this->render('buyback/lookup.html.twig', Array ( 'type_name' => $type->getTypeName(), 'amarr' => $amarrData, 'source_system' => $bb_source_id,
                                        'source_type' => $bb_source_type, 'source_stat' => $bb_source_stat, 'typeid' => $type->getTypeID(),
                                        'jita' => $jitaData, 'dodixie' => $dodixieData, 'rens' => $rensData, 'hek' => $hekData, 'value' => $value,
                                        'details' => $priceDetails, 'market_group' => $market_group, 'is_priced' => $isPricedByMinerals, 'options' => $options,
                'group' => $group));
            return $template;

        } else {

            // Get item name searched for
            $name = $request->request->get('id');

            // Get all matching types
            $types = $this->getDoctrine()->getRepository('EveBundle:TypeEntity', 'evedata')->findAllLikeName($name);

            $template = $this->render('elements/searchResultsByType.html.twig', Array ( 'items' => $types ));
            return $template;
        }
    }

    /**
     * @Route("/guest/buyback", name="guest_buyback")
     */
    public function guestBuybackIndexAction(Request $request)
    {
        // Get Eve Central Online Status
        $eveCentralOK = $this->get("helper")->getSetting("eveCentralOK");

        // Create Buyback Form
        $bb = new BuyBackModel();
        $form = $this->createForm(BuyBackForm::class, $bb);

        // Handle Form
        $form->handleRequest($request);

        // If form is valid
        if ($form->isValid() && $form->isSubmitted())
        {
            $types = $this->getDoctrine()->getRepository('EveBundle:TypeEntity', 'evedata');
            $cache = $this->getDoctrine()->getRepository('AppBundle:CacheEntity', 'default');
            $items = array();
            $typeids = array();

            $items = $this->get('parser')->GetLineItemsFromPasteData($bb->getItems());

            if(!$this->get('market')->PopulateLineItems($items, true))
            {
                $template = $this->render('elements/error_modal.html.twig', Array( 'message' => "No Prices Found"));
                return $template;
            }

            //$priceLookup = $this->get('market')->GetMarketPrices($typeids);

            //if(!is_array($priceLookup)) {

                //$this->addFlash('error', "No pricing information found.  Please Eve mail 'Lorvulk Ormand' in game if you feel this is in error.");
                //return $this->redirectToRoute('guest_buyback');
            //}

            $totalValue = 0;

            foreach($items as $lineItem) {
                //$taxAmount = ;
                $totalValue += $lineItem->getNetPrice();
            }

            if($items == null)
            {
                $this->addFlash('error', "No valid items found.  Please Eve mail 'Lorvulk Ormand' in game if you feel this is in error.");
                return $this->redirectToRoute('guest_buyback');
            }

            $formH = $this->createForm(BuyBackHiddenForm::class, $bb, array( 'action' => $this->generateUrl('guest_accept_offer')));
            $formH->handleRequest($request);

            return $this->render('buyback/step_two.html.twig', array('items' => $items, 'total' => $totalValue, 'rawitems' => $bb->getItems(), 'form' => $formH->createView() ));
        }

        return $this->render('buyback/index.html.twig', array('form' => $form->createView(), 'eveCentralOK' => $eveCentralOK ));
    }

    /**
     * @Route("/guest/accept", name="guest_accept_offer")
     */
    public function guestBuybackAcceptAction(Request $request)
    {
        // Create Buyback Form
        $bb = new BuyBackModel();
        $form = $this->createForm(BuyBackHiddenForm::class, $bb);

        // Handle Form
        $form->handleRequest($request);

        // If form is valid
        if ($form->isValid() && $form->isSubmitted())
        {
            $types = $this->getDoctrine()->getRepository('EveBundle:TypeEntity', 'evedata');
            $cache = $this->getDoctrine()->getRepository('AppBundle:CacheEntity', 'default');
            $items = array();
            $typeids = array();

            $items = $this->get('parser')->GetLineItemsFromPasteData($bb->getItems(), true);

            $em = $this->getDoctrine()->getManager('default');

            if(!$this->get('market')->PopulateLineItems($items)) {

                $this->addFlash('error', "No pricing information found.  Please Eve mail 'Lorvulk Ormand' in game if you feel this is in error.");
                return $this->redirectToRoute('guest_buyback');
            }

            $totalValue = 0;
            $gross = 0;
            $net = 0;

            foreach($items as $lineItem)
            {
                $gross +=  $lineItem->getGrossPrice();
                $net += $lineItem->getNetPrice();
            }

            $transaction = new TransactionEntity();
            //$transaction->setUser($this->getUser());

            $transaction->setType("P");

            $transaction->setIsComplete(false);
            $transaction->setOrderId($transaction->getType() . uniqid());
            $transaction->setGross(0);
            $transaction->setNet(0);
            $transaction->setCreated(new \DateTime("now"));
            $transaction->setStatus("Pending");
            $em->persist($transaction);

            foreach($items as $lineItem)
            {
                $transaction->addLineItem($lineItem);
                $em->persist($lineItem);
            }

            $transaction->setGross($gross);
            $transaction->setNet($net);

            //$em->persist($transaction);
            $em->flush();

            return $this->render('buyback/step_three.html.twig', array('total_value' => $net, 'auth_code' => $transaction->getOrderId()));
        }
    }

    /**
     * @Route("/ajax_type_list", name="ajax_type_list")
     */
    public function ajax_TypeListAction(Request $request)
    {
        $query = $request->request->get("query");
        $limit = $request->request->get("limit");

        $types = $this->getDoctrine()->getRepository('EveBundle:TypeEntity','evedata')->findAllLikeName($query);

        $results = array();

        if(count($types) < $limit) { $limit = count($types); }

        for($count = 0;$count < $limit;$count++)
        {
            $result = array();
            $result['id'] = $types[$count]->getTypeId();
            $result['value'] = $types[$count]->getTypeName();
            $results[] = $result;
        }

        return new JsonResponse($results);
    }

    /**
     * @Route("/ajax_market_list", name="ajax_market_list")
     */
    public function ajax_MarketListAction(Request $request)
    {
        $query = $request->request->get("query");
        $limit = $request->request->get("limit");

        $groups = $this->getDoctrine()->getRepository('EveBundle:MarketGroupsEntity','evedata')->findAllLikeName($query);

        $results = array();
        for($count = 0;$count < count($groups);$count++)
        {
            $result = array();
            $result['id'] = $groups[$count]->getMarketGroupId();
            $result['value'] = $groups[$count]->getMarketGroupName();

            $results[] = $result;

            if($count >= $limit) {break;}
        }

        return new JsonResponse($results);
    }

    /**
     * @Route("/ajax_group_list", name="ajax_group_list")
     */
    public function ajax_GroupListAction(Request $request)
    {
        $query = $request->request->get("query");
        $limit = $request->request->get("limit");

        $groups = $this->getDoctrine()->getRepository('EveBundle:GroupsEntity','evedata')->findAllLikeName($query);

        $results = array();
        for($count = 0;$count < count($groups);$count++)
        {
            $result = array();
            $result['id'] = $groups[$count]->getGroupId();
            $result['value'] = $groups[$count]->getGroupName();

            $results[] = $result;

            if($count >= $limit) {break;}
        }

        return new JsonResponse($results);
    }
}
