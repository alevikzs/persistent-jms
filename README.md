## Persistent JMS
JMS array collection handler for symfony doctrine persistent collection support.
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