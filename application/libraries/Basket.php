<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Basket {

    protected $cart_contents = array();
    protected $CI;

    public function __construct(){
        $this->CI =& get_instance();
        $this->CI->load->library('session');
        $this->cart_contents = !empty($this->CI->session->cart_contents)?$this->CI->session->cart_contents:NULL;
        if ($this->cart_contents === NULL){
            // set some base values
            $this->cart_contents  = array(
                'cart_total' => 0,
                'total_items' => 0
            );
        }
    }

    /**
     * Cart Contents: Returns the entire cart array
     * @param	bool
     * @return	array
     */
    public function contents(){
        // rearrange the newest first
        $cart = array_reverse($this->cart_contents);

        // remove these so they don't create a problem when showing the cart table
        unset($cart['total_items']);
        unset($cart['cart_total']);

        return $cart;
    }

    /**
     * Get cart item: Returns a specific cart item details
     * @param	string	$row_id
     * @return	array
     */
    public function get_item($row_id){
        return (in_array($row_id, array('total_items', 'cart_total'), TRUE) OR ! isset($this->cart_contents[$row_id]))
        ? FALSE
        : $this->cart_contents[$row_id];
    }

    /**
     * Total Items: Returns the total item count
     * @return	int
     */
    public function total_items(){
        return $this->cart_contents['total_items'];
    }

    /**
     * Cart Total: Returns the total price
     * @return	int
     */
    public function total(){
        return $this->cart_contents['cart_total'];
    }

    /**
     * Insert items into the cart and save it to the session
     * @param	array
     * @return	bool
     */
    public function insert($item = array()){
        if(!is_array($item) OR count($item) === 0){
            return FALSE;
        }else{
            if(!isset($item['id'], $item['name'], $item['img'], $item['price'], $item['qty'], $item['maxqty'])){
                return FALSE;
            }else{
                /*
                 * Insert Item
                 */
                // prep the quantity
                $item['qty'] = (float) $item['qty'];
                if($item['qty'] == 0){
                    return FALSE;
                }
                // prep the price
                $item['price'] = (float) $item['price'];
                // create a unique identifier for the item being inserted into the cart
                $rowid = md5($item['id']);
                // get quantity if it's already there and add it on
                $old_qty = isset($this->cart_contents[$rowid]['qty']) ? (int) $this->cart_contents[$rowid]['qty'] : 0;
                // re-create the entry with unique identifier and updated quantity
                $item['rowid'] = $rowid;
                // check if it exceed the maxqty of the item
                if($old_qty == $item['maxqty']){
                    return FALSE;
                }
                $item['qty'] += $old_qty;
                $this->cart_contents[$rowid] = $item;
                // save Cart Item
                if($this->save_cart()){
                    return isset($rowid) ? $rowid : TRUE;
                }else{
                    return FALSE;
                }
            }
        }
    }

    /**
     * Update the cart
     * @param	array
     * @return	bool
     */
    public function update($item = array()){
        if (!is_array($item) OR count($item) === 0){
            return FALSE;
        }else{
            if (!isset($item['rowid'], $this->cart_contents[$item['rowid']])){
                return FALSE;
            }else{
                // prep the quantity
                if(isset($item['qty'], $item['maxqty'])){
                    $item['qty'] = (float) $item['qty'];
                    // remove the item from the cart, if quantity is zero

                    if ($item['qty'] == 0){
                        unset($this->cart_contents[$item['rowid']]);
                        $this->save_cart();
                        return TRUE;
                    }
                }

                $old_qty = isset($this->cart_contents[$item['rowid']]['qty']) ? (int) $this->cart_contents[$item['rowid']]['qty'] : 0;

                if ($old_qty == $item['maxqty']){
                    return FALSE;
                }
                // find updatable keys
                $keys = array_intersect(array_keys($this->cart_contents[$item['rowid']]), array_keys($item));
                // prep the price
                if(isset($item['price'])){
                    $item['price'] = (float) $item['price'];
                }
                // product id & name shouldn't be changed
                foreach(array_diff($keys, array('id', 'name')) as $key){
                    $this->cart_contents[$item['rowid']][$key] = $item[$key];
                }

                // save cart data
                $this->save_cart();
                return TRUE;
            }
        }
    }

    /**
     * Save the cart array to the session
     * @return	bool
     */
    protected function save_cart(){

        $this->cart_contents['total_items'] = $this->cart_contents['cart_total'] = 0;

        foreach ($this->cart_contents as $key => $val){
            // make sure the array contains the proper indexes
            if(!is_array($val) OR !isset($val['price'], $val['qty'])){
                continue;
            }

            $this->cart_contents['cart_total'] += ($val['price'] * $val['qty']);
            $this->cart_contents['total_items'] += $val['qty'];
            $this->cart_contents[$key]['subtotal'] = ($this->cart_contents[$key]['price'] * $this->cart_contents[$key]['qty']);
        }

        // if cart empty, delete it from the session
        if(count($this->cart_contents) <= 2){
            $this->CI->session->unset_userdata('cart_contents');
            return FALSE;
        }else{
            $this->CI->session->cart_contents = $this->cart_contents;
            return TRUE;
        }
    }

    /**
     * Remove Item: Removes an item from the cart
     * @param	int
     * @return	bool
     */
    public function remove($row_id){
        // unset & save
        unset($this->cart_contents[$row_id]);
        $this->save_cart();
        return TRUE;
    }

    /**
     * Destroy the cart: Empties the cart and destroy the session
     * @return	void
     */
    public function destroy(){
        $this->cart_contents = array('cart_total' => 0, 'total_items' => 0);
        $this->CI->session->unset_userdata('cart_contents');
    }
}