use std::{borrow::BorrowMut, cell::RefCell, rc::Rc};

// first, could not compile
// struct Node {
//     value: i32,
//     next: Option<Node>
// }

// struct TransactionLog {
//     head: Option<Node>,
//     tail: Option<Node>,
//     pub length: u64,
// }

// second
// type SingleLink = Option<Rc<RefCell<Node>>>;

// struct Node {
//     value: i32,
//     next: SingleLink
// }

// struct TransactionLog {
//     head: SingleLink,
//     tail: SingleLink,
//     pub length: u64,
// }

// third
type SingleLink = Option<Rc<RefCell<Node>>>;

struct Node {
    value: String,
    next: SingleLink
}

struct TransactionLog {
    head: SingleLink,
    tail: SingleLink,
    pub length: u64,
}

impl Node {
    fn new(value: String) -> Rc<RefCell<Node>> {
        Rc::new(RefCell::new(Node{
            value,
            next: None,
        }))
    }
}

impl TransactionLog {
    pub fn new_empty() -> Self {
        TransactionLog {
            head: None,
            tail: None,
            length: 0,
        }
    }

    pub fn append(&mut self, value: String) {
        let new = Node::new(value);

        match self.tail.take() {
            Some(old) => {
               (*old).borrow_mut().next = Some(new.clone())
            },
            None => self.head = Some(new.clone())
        };
        self.length += 1;
        self.tail = Some(new);
    }

    pub fn pop(&mut self) -> Option<String>{
        self.head.take().map(|head| {
            if let Some(next) = (*head).borrow_mut().next.take() {
                self.head = Some(next);
            } else {
                self.tail.take();
            }
            self.length -= 1;
            Rc::try_unwrap(head).ok().expect("Something is terribly wrong").into_inner().value
        })
    }

}


#[cfg(test)]
mod tests {
    #[test]
    fn it_works() {
        assert_eq!(2 + 2, 4);
    }
}
