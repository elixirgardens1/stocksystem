
# Table of Contents

1.  [Stock Control](#orgb97f0ee)
    1.  [System Structure](#org2f74348)
        1.  [Located](#org485a50a)
        2.  [Database](#orgb66813f)
        3.  [Dependencies](#org291656f)
    2.  [System Design](#org7efbc98)
        1.  [Languages](#org8b3cc19)


<a id="orgb97f0ee"></a>

# Stock Control

The stock control system is a tool to track the products we are selling or have sold previously.


<a id="org2f74348"></a>

## System Structure


<a id="org485a50a"></a>

### Located

<https://deepthought:8080/stocksystem:8080/dist/>


<a id="orgb66813f"></a>

### Database

X:\stocksystem\PHPAPI\stock<sub>control.db3</sub>


<a id="org291656f"></a>

### Dependencies

1.  Transparency

    The stock control is linked to @matrixCodes.db3 database located at Z:\FESP-REFACTOR\FespMVC\Modules\Transparanecy\matrixCodes.db3. Stock control has the Transparency page which is used for the management of protected asins sold on amazon. New Codes are also inserted from this page and various stats about the asins are available.


<a id="org7efbc98"></a>

## System Design


<a id="org8b3cc19"></a>

### Languages

1.  PHP

    PHP is used for retrieving and updating data in the backend databases, all of the php used within the project is located within the /PHPAPI folder located in the root of the stocksystem folder. Below I will discuss the purpose of all the php based files the system uses.
    
    1.  QueryController.php
    
        This processes all the GET requests from the Vue frontend, the axiosGet.js file you can see below passes the a $<sub>GET</sub> argument and the QueryController checks the argument passed and returns the relevant data.
        
            // Located: /src/composables/axiosGet.js
            import axios from "axios";
            
            export function axiosGet(type) {
              const promise = axios.get(
                `http://localhost/Ryan/Projects/stocksystem/PHPAPI/QueryController.php?${type}`
              );
            
              const dataPromise = promise.then((response) => response.data);
            
              return dataPromise;
            }
        
        The QueryController need to be improved by actually building a proper API controller for the basic queries that are common across all the pages on the system, for example, product information is used on the majority of pages so should be called once using a proper API call, rather than pulling everytime.
    
    2.  StockPost.php
    
        Similar in structure to the QueryController, processes POST requests from the Vue frontend, the axiosPost.js file you can see below takes the url arguments and POSTED data and updates the backend databases.

