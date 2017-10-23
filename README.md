## Persistent JMS
#### What is it?
This package is based on [JMS Serializer](https://github.com/schmittjoh/serializer) and resolve symfony doctrine persistent collection support problem. 
When you use deserialization, original library is not support cascade and orphanRemoval relations functionality.
#### Installation
```
$ composer require alevikzs/persistent-jms
```
#### How to use
In your symfony services.yml config need to add:
```
jms_serializer.object_constructor:
    alias: jms_serializer.doctrine_object_constructor
    public: false
jms_serializer.array_collection_handler:
    class: PersistentJMS\ArrayCollectionHandler
    tags:
        - { name: jms_serializer.subscribing_handler }
```